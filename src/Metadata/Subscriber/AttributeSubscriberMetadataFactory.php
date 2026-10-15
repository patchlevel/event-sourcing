<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Subscriber;

use Patchlevel\EventSourcing\Attribute\BatchBegin;
use Patchlevel\EventSourcing\Attribute\BatchFlush;
use Patchlevel\EventSourcing\Attribute\BatchRollback;
use Patchlevel\EventSourcing\Attribute\BatchShouldFlush;
use Patchlevel\EventSourcing\Attribute\BatchState;
use Patchlevel\EventSourcing\Attribute\Cleanup;
use Patchlevel\EventSourcing\Attribute\DisableEventEmitting;
use Patchlevel\EventSourcing\Attribute\EnableEventEmittingDuringBoot;
use Patchlevel\EventSourcing\Attribute\OnFailed;
use Patchlevel\EventSourcing\Attribute\Partition;
use Patchlevel\EventSourcing\Attribute\RetryStrategy;
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\Subscription\Subscription;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

use function array_key_exists;
use function array_map;
use function count;
use function str_contains;

final class AttributeSubscriberMetadataFactory implements SubscriberMetadataFactory
{
    private readonly TypeResolver $typeResolver;

    public function __construct()
    {
        $this->typeResolver = TypeResolver::create();
    }

    /** @var array<class-string, SubscriberMetadata> */
    private array $subscriberMetadata = [];

    /** @param class-string $subscriber */
    public function metadata(string $subscriber): SubscriberMetadata
    {
        if (array_key_exists($subscriber, $this->subscriberMetadata)) {
            return $this->subscriberMetadata[$subscriber];
        }

        $reflector = new ReflectionClass($subscriber);

        $attributes = $reflector->getAttributes(Subscriber::class, ReflectionAttribute::IS_INSTANCEOF);

        if ($attributes === []) {
            throw new ClassIsNotASubscriber($subscriber);
        }

        $subscriberInfo = $attributes[0]->newInstance();

        $methods = $reflector->getMethods();

        $subscribeMethods = [];
        $setupMethod = null;
        $teardownMethod = null;
        $cleanupMethod = null;
        $failedMethod = null;
        $beginBatchMethod = null;
        $flushMethod = null;
        $flushAfterMessages = null;
        $shouldFlushMethod = null;
        $rollbackBatchMethod = null;

        foreach ($methods as $method) {
            $attributes = $method->getAttributes(Subscribe::class);

            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                $eventClass = $instance->eventClass;

                if (array_key_exists($eventClass, $subscribeMethods)) {
                    throw DuplicateSubscribeMethod::duplicateEvent(
                        $subscriber,
                        $eventClass,
                        $subscribeMethods[$eventClass]->name,
                        $method->getName(),
                    );
                }

                $subscribeMethods[$eventClass] = $this->subscribeMethod($method);
            }

            if ($method->getAttributes(BatchBegin::class)) {
                if ($beginBatchMethod !== null) {
                    throw new DuplicateBeginBatchMethod(
                        $subscriber,
                        $beginBatchMethod,
                        $method->getName(),
                    );
                }

                $beginBatchMethod = $method->getName();
            }

            $flushAttributes = $method->getAttributes(BatchFlush::class);

            if ($flushAttributes !== []) {
                if ($flushMethod !== null) {
                    throw new DuplicateFlushMethod(
                        $subscriber,
                        $flushMethod,
                        $method->getName(),
                    );
                }

                $flushMethod = $method->getName();
                $flushAfterMessages = $flushAttributes[0]->newInstance()->afterMessages;
            }

            if ($method->getAttributes(BatchShouldFlush::class)) {
                if ($shouldFlushMethod !== null) {
                    throw new DuplicateShouldFlushMethod(
                        $subscriber,
                        $shouldFlushMethod,
                        $method->getName(),
                    );
                }

                $shouldFlushMethod = $method->getName();
            }

            if ($method->getAttributes(BatchRollback::class)) {
                if ($rollbackBatchMethod !== null) {
                    throw new DuplicateRollbackBatchMethod(
                        $subscriber,
                        $rollbackBatchMethod,
                        $method->getName(),
                    );
                }

                $rollbackBatchMethod = $method->getName();
            }

            if ($method->getAttributes(OnFailed::class)) {
                if ($failedMethod !== null) {
                    throw new DuplicateFailedMethod(
                        $subscriber,
                        $failedMethod,
                        $method->getName(),
                    );
                }

                $failedMethod = $method->getName();
            }

            if ($method->getAttributes(Setup::class)) {
                if ($setupMethod !== null) {
                    throw new DuplicateSetupMethod(
                        $subscriber,
                        $setupMethod,
                        $method->getName(),
                    );
                }

                $setupMethod = $method->getName();
            }

            if ($method->getAttributes(Cleanup::class)) {
                if ($cleanupMethod !== null) {
                    throw new DuplicateCleanupMethod(
                        $subscriber,
                        $cleanupMethod,
                        $method->getName(),
                    );
                }

                if ($teardownMethod !== null) {
                    throw new MixedTeardownAndCleanupMethods(
                        $subscriber,
                        $teardownMethod,
                        $method->getName(),
                    );
                }

                $cleanupMethod = $method->getName();
            }

            if (!$method->getAttributes(Teardown::class)) {
                continue;
            }

            if ($teardownMethod !== null) {
                throw new DuplicateTeardownMethod(
                    $subscriber,
                    $teardownMethod,
                    $method->getName(),
                );
            }

            if ($cleanupMethod !== null) {
                throw new MixedTeardownAndCleanupMethods(
                    $subscriber,
                    $method->getName(),
                    $cleanupMethod,
                );
            }

            $teardownMethod = $method->getName();
        }

        if (array_key_exists(Subscribe::ALL, $subscribeMethods) && count($subscribeMethods) > 1) {
            throw DuplicateSubscribeMethod::mixedWithAll($subscriber);
        }

        $usesBatching = $beginBatchMethod !== null
            || $flushMethod !== null
            || $shouldFlushMethod !== null
            || $rollbackBatchMethod !== null
            || $this->hasBatchArgument($subscribeMethods);

        $batch = null;

        if ($usesBatching) {
            if ($flushMethod === null) {
                throw IncompleteBatchMethods::missingFlushMethod($subscriber);
            }

            $batch = new BatchMetadata(
                $flushMethod,
                $beginBatchMethod,
                $shouldFlushMethod,
                $rollbackBatchMethod,
                $flushAfterMessages,
            );
        }

        $partitionAttributes = $reflector->getAttributes(Partition::class);
        $partitionMetadata = null;

        if ($partitionAttributes !== []) {
            if (str_contains($subscriberInfo->id, Subscription::PARTITION_SEPARATOR)) {
                throw new InvalidPartitionedSubscriberId($subscriber, $subscriberInfo->id);
            }

            $partition = $partitionAttributes[0]->newInstance();
            $partitionMetadata = new PartitionMetadata($partition->maxConsecutiveFailures, $partition->removeIdleAfter);
        }

        $metadata = new SubscriberMetadata(
            $subscriberInfo->id,
            $subscriberInfo->group,
            $subscriberInfo->runMode,
            $subscribeMethods,
            $setupMethod,
            $teardownMethod,
            $failedMethod,
            $this->retryStrategy($reflector),
            $cleanupMethod,
            $reflector->getAttributes(EnableEventEmittingDuringBoot::class) !== [],
            $reflector->getAttributes(DisableEventEmitting::class) !== [],
            $batch,
            $partitionMetadata,
        );

        $this->subscriberMetadata[$subscriber] = $metadata;

        return $metadata;
    }

    private function subscribeMethod(ReflectionMethod $method): SubscribeMethodMetadata
    {
        $arguments = [];

        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type === null) {
                throw ArgumentTypeNotSupported::missingType(
                    $method->getDeclaringClass()->getName(),
                    $method->getName(),
                    $parameter->getName(),
                );
            }

            $arguments[] = new ArgumentMetadata(
                $parameter->getName(),
                $this->typeResolver->resolve($type),
                array_map(
                    static fn (ReflectionAttribute $attribute): object => $attribute->newInstance(),
                    $parameter->getAttributes(),
                ),
            );
        }

        return new SubscribeMethodMetadata(
            $method->getName(),
            $arguments,
        );
    }

    /** @param array<class-string|"*", SubscribeMethodMetadata> $subscribeMethods */
    private function hasBatchArgument(array $subscribeMethods): bool
    {
        foreach ($subscribeMethods as $subscribeMethod) {
            foreach ($subscribeMethod->arguments as $argument) {
                if ($argument->hasAttribute(BatchState::class)) {
                    return true;
                }
            }
        }

        return false;
    }

    private function retryStrategy(ReflectionClass $reflector): string|null
    {
        $attributes = $reflector->getAttributes(RetryStrategy::class);

        if ($attributes === []) {
            return null;
        }

        $instance = $attributes[0]->newInstance();

        return $instance->name;
    }
}
