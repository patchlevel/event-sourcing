<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Subscriber;

use Patchlevel\EventSourcing\Attribute\Batch;
use Patchlevel\EventSourcing\Attribute\BeginBatch;
use Patchlevel\EventSourcing\Attribute\CommitBatch;
use Patchlevel\EventSourcing\Attribute\RollbackBatch;
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Attribute\Teardown;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

use function array_key_exists;

final class AttributeSubscriberMetadataFactory implements SubscriberMetadataFactory
{
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
        $createMethod = null;
        $dropMethod = null;

        $attributes = $reflector->getAttributes(Batch::class);

        $batch = $attributes !== [];
        $beginBatchMethod = null;
        $commitBatchMethod = null;
        $rollbackBatchMethod = null;

        foreach ($methods as $method) {
            $attributes = $method->getAttributes(Subscribe::class);

            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();
                $eventClass = $instance->eventClass;

                $subscribeMethods[$eventClass][] = $this->subscribeMethod($method);
            }

            if ($method->getAttributes(Setup::class)) {
                if ($createMethod !== null) {
                    throw new DuplicateSetupMethod(
                        $subscriber,
                        $createMethod,
                        $method->getName(),
                    );
                }

                $createMethod = $method->getName();
            }

            if ($method->getAttributes(Teardown::class)) {
                if ($dropMethod !== null) {
                    throw new DuplicateTeardownMethod(
                        $subscriber,
                        $dropMethod,
                        $method->getName(),
                    );
                }

                $dropMethod = $method->getName();
            }

            if ($method->getAttributes(BeginBatch::class)) {
                if ($beginBatchMethod !== null) {
                    throw new DuplicateBeginBatchMethod(
                        $subscriber,
                        $beginBatchMethod,
                        $method->getName(),
                    );
                }

                $beginBatchMethod = $method->getName();
            }

            if ($method->getAttributes(CommitBatch::class)) {
                if ($commitBatchMethod !== null) {
                    throw new DuplicateBeginBatchMethod(
                        $subscriber,
                        $commitBatchMethod,
                        $method->getName(),
                    );
                }

                $commitBatchMethod = $method->getName();
            }

            if ($method->getAttributes(RollbackBatch::class)) {
                if ($rollbackBatchMethod !== null) {
                    throw new DuplicateBeginBatchMethod(
                        $subscriber,
                        $rollbackBatchMethod,
                        $method->getName(),
                    );
                }

                $rollbackBatchMethod = $method->getName();
            }
        }

        $metadata = new SubscriberMetadata(
            $subscriberInfo->id,
            $subscriberInfo->group,
            $subscriberInfo->runMode,
            $subscribeMethods,
            $createMethod,
            $dropMethod,
            $batch,
            $beginBatchMethod,
            $commitBatchMethod,
            $rollbackBatchMethod,
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

            if (!$type instanceof ReflectionNamedType) {
                throw ArgumentTypeNotSupported::onlyNamedTypeSupported(
                    $method->getDeclaringClass()->getName(),
                    $method->getName(),
                    $parameter->getName(),
                );
            }

            $arguments[] = new ArgumentMetadata(
                $parameter->getName(),
                $type->getName(),
            );
        }

        return new SubscribeMethodMetadata(
            $method->getName(),
            $arguments,
        );
    }
}
