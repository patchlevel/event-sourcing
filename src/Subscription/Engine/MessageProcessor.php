<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Subscriber\ArgumentMetadata;
use Patchlevel\EventSourcing\Metadata\Subscriber\PartitionMetadata;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessage;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessageError;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessageSuccess;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\ArgumentResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\ArgumentResolverContext;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\EventArgumentResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\MessageArgumentResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\RecordedOnArgumentResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessor;
use Patchlevel\EventSourcing\Subscription\Subscriber\NoSuitableResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Subscription\SubscriptionType;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Throwable;
use WeakMap;

use function array_merge;
use function array_values;
use function crc32;
use function is_array;
use function is_string;
use function iterator_to_array;
use function sprintf;
use function str_contains;
use function strlen;

/** @internal */
final class MessageProcessor
{
    /** @var list<ArgumentResolver> */
    private readonly array $argumentResolvers;

    /** @var array<string, array<class-string, array<string, list<ArgumentResolver>>>> */
    private array $resolverCache = [];

    /**
     * Known partition keys per claimed main subscription of a partitioned subscriber. The state is reset at the start of
     * every run (see reset()), so the partitions are reloaded once per claim and never go stale. A store may return the
     * same object for every claim, so the reset must not rely on a fresh object.
     *
     * @var WeakMap<Subscription, array<string, true>>
     */
    private WeakMap $knownPartitions;

    /** @var WeakMap<Subscription, true> */
    private WeakMap $discoveredPartitions;

    /** @param iterable<ArgumentResolver>|list<ArgumentResolver> $argumentResolvers */
    public function __construct(
        private readonly SubscriberAccessorRepository $subscriberRepository,
        private readonly SubscriptionManager $subscriptionManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        iterable $argumentResolvers = [],
        private readonly LoggerInterface|null $logger = null,
    ) {
        $this->argumentResolvers = array_merge(
            // the check for array is required before PHP 8.2
            array_values(is_array($argumentResolvers) ? $argumentResolvers : iterator_to_array($argumentResolvers)),
            [
                new MessageArgumentResolver(),
                new EventArgumentResolver(),
                new RecordedOnArgumentResolver(),
            ],
        );

        $this->knownPartitions = new WeakMap();
        $this->discoveredPartitions = new WeakMap();
    }

    public function process(int $index, Message $message, Subscription $subscription): Error|null
    {
        $subscriber = $this->subscriberRepository->get($subscription->subscriberId());

        if (!$subscriber) {
            throw SubscriberNotFound::forSubscriptionId($subscription->id());
        }

        $subscribeMethods = $subscriber->subscribeMethods($message->event()::class);

        if ($subscribeMethods === []) {
            $this->logger?->debug(
                sprintf(
                    'Subscription Engine: Subscriber "%s" for "%s" has no subscribe methods for "%s", continue.',
                    $subscriber::class,
                    $subscription->id(),
                    $message->event()::class,
                ),
            );

            $this->success($index, $message, $subscription);

            return null;
        }

        if ($subscription->type() === SubscriptionType::Main && $subscriber->metadata()->partition !== null) {
            return $this->route($index, $message, $subscription, $subscriber);
        }

        if ($subscription->type() === SubscriptionType::Partition) {
            try {
                $partitionKey = $this->partitionKey($message, $subscription, $subscriber);
            } catch (Throwable $e) {
                return $this->error($index, $message, $subscription, $subscriber, $e);
            }

            if ($partitionKey !== $subscription->partitionKey()) {
                $this->success($index, $message, $subscription, true);

                return null;
            }
        }

        try {
            $event = new OnHandleMessage(
                $subscription,
                $message,
            );

            $this->eventDispatcher->dispatch($event);

            $context = new ArgumentResolverContext($message, $subscription, $subscriber->metadata());

            foreach ($subscribeMethods as $subscribeMethod) {
                $arguments = $this->resolveArguments(
                    $subscription->subscriberId(),
                    $message->event()::class,
                    $subscriber->subscriber()::class,
                    $subscribeMethod->name,
                    $subscribeMethod->arguments,
                    $context,
                );

                $subscriber->subscriber()->{$subscribeMethod->name}(...$arguments);
            }
        } catch (Throwable $e) {
            return $this->error($index, $message, $subscription, $subscriber, $e);
        }

        $this->success($index, $message, $subscription);

        $this->logger?->debug(
            sprintf(
                'Subscription Engine: Subscriber "%s" for "%s" processed the event "%s".',
                $subscriber::class,
                $subscription->id(),
                $message->event()::class,
            ),
        );

        return null;
    }

    /**
     * Forgets the partition state of the given subscription, must be called before a claimed subscription is processed.
     */
    public function reset(Subscription $subscription): void
    {
        unset($this->knownPartitions[$subscription], $this->discoveredPartitions[$subscription]);
    }

    /**
     * Returns whether the given main subscription has created new partitions since it was claimed.
     */
    public function hasDiscoveredPartitions(Subscription $subscription): bool
    {
        return isset($this->discoveredPartitions[$subscription]);
    }

    /**
     * The main subscription of a partitioned subscriber never calls the subscriber itself. It walks the stream, resolves the
     * partition key of every message and creates a partition subscription for each new key. The new
     * partition starts just before the message that introduced it and inherits the status of the
     * main subscription, so it is booted or run by the next claim.
     */
    private function route(
        int $index,
        Message $message,
        Subscription $subscription,
        MetadataSubscriberAccessor $subscriber,
    ): Error|null {
        try {
            $partitionKey = $this->partitionKey($message, $subscription, $subscriber);
            $knownPartitions = $this->knownPartitions($subscription, $subscriber);

            if (!isset($knownPartitions[$partitionKey])) {
                $this->subscriptionManager->add(
                    Subscription::partition(
                        $subscription,
                        $partitionKey,
                        $this->shard($partitionKey, $subscriber),
                        $index - 1,
                    ),
                );

                $knownPartitions[$partitionKey] = true;
                $this->knownPartitions[$subscription] = $knownPartitions;
                $this->discoveredPartitions[$subscription] = true;

                $this->logger?->info(
                    sprintf(
                        'Subscription Engine: Partition "%s" of subscriber "%s" discovered.',
                        $partitionKey,
                        $subscription->subscriberId(),
                    ),
                );
            }
        } catch (Throwable $e) {
            return $this->error($index, $message, $subscription, $subscriber, $e);
        }

        $this->success($index, $message, $subscription);

        return null;
    }

    private function partitionKey(
        Message $message,
        Subscription $subscription,
        MetadataSubscriberAccessor $subscriber,
    ): string {
        $partition = $this->partitionMetadata($subscription, $subscriber);
        $subscriberId = $subscription->subscriberId();

        if (
            $subscription->type() === SubscriptionType::Partition
            && $subscription->shard() !== $this->shard((string)$subscription->partitionKey(), $subscriber)
        ) {
            throw new PartitionShardsChanged($subscriberId);
        }

        $arguments = $this->resolveArguments(
            $subscriberId,
            $message->event()::class,
            $subscriber->subscriber()::class,
            $partition->name,
            $partition->arguments,
            new ArgumentResolverContext($message, $subscription, $subscriber->metadata()),
        );

        $partitionKey = $subscriber->subscriber()->{$partition->name}(...$arguments);

        if (!is_string($partitionKey)) {
            throw InvalidPartitionKey::notAString($subscriberId, $partitionKey);
        }

        if ($partitionKey === '') {
            throw InvalidPartitionKey::empty($subscriberId);
        }

        if (str_contains($partitionKey, Subscription::PARTITION_SEPARATOR)) {
            throw InvalidPartitionKey::containsSeparator($subscriberId, $partitionKey);
        }

        if (strlen($subscriberId . Subscription::PARTITION_SEPARATOR . $partitionKey) > Subscription::MAX_ID_LENGTH) {
            throw InvalidPartitionKey::tooLong($subscriberId, $partitionKey);
        }

        return $partitionKey;
    }

    /** @return array<string, true> */
    private function knownPartitions(Subscription $subscription, MetadataSubscriberAccessor $subscriber): array
    {
        if (isset($this->knownPartitions[$subscription])) {
            return $this->knownPartitions[$subscription];
        }

        $partitions = $this->subscriptionManager->find(
            new SubscriptionCriteria(
                subscriberIds: [$subscription->subscriberId()],
                types: [SubscriptionType::Partition],
            ),
        );

        $knownPartitions = [];

        foreach ($partitions as $partition) {
            $partitionKey = (string)$partition->partitionKey();

            if ($partition->shard() !== $this->shard($partitionKey, $subscriber)) {
                throw new PartitionShardsChanged($subscription->subscriberId());
            }

            $knownPartitions[$partitionKey] = true;
        }

        return $this->knownPartitions[$subscription] = $knownPartitions;
    }

    private function shard(string $partitionKey, MetadataSubscriberAccessor $subscriber): int|null
    {
        $shards = $subscriber->metadata()->partition?->shards;

        if ($shards === null) {
            return null;
        }

        return crc32($partitionKey) % $shards;
    }

    private function partitionMetadata(
        Subscription $subscription,
        MetadataSubscriberAccessor $subscriber,
    ): PartitionMetadata {
        $partition = $subscriber->metadata()->partition;

        if ($partition === null) {
            throw new UnexpectedError(
                sprintf(
                    'Subscription "%s" is a partition, but its subscriber has no partition method.',
                    $subscription->id(),
                ),
            );
        }

        return $partition;
    }

    private function success(int $index, Message $message, Subscription $subscription, bool $skipped = false): void
    {
        $event = new OnHandleMessageSuccess($subscription, $message, $index, skipped: $skipped);
        $this->eventDispatcher->dispatch($event);

        if (!$event->shouldChangePosition) {
            return;
        }

        $subscription->changePosition($index);
    }

    private function error(
        int $index,
        Message $message,
        Subscription $subscription,
        MetadataSubscriberAccessor $subscriber,
        Throwable $throwable,
    ): Error {
        $this->logger?->error(
            sprintf(
                'Subscription Engine: Subscriber "%s" for "%s" could not process the event "%s": %s',
                $subscriber::class,
                $subscription->id(),
                $message->event()::class,
                $throwable->getMessage(),
            ),
        );

        $this->eventDispatcher->dispatch(
            new OnHandleMessageError(
                $subscription,
                $throwable,
                $message,
                $index,
            ),
        );

        return new Error(
            $subscription->id(),
            $throwable->getMessage(),
            $throwable,
        );
    }

    /**
     * @param class-string           $eventClass
     * @param class-string           $subscriberClass
     * @param list<ArgumentMetadata> $arguments
     *
     * @return list<mixed>
     */
    private function resolveArguments(
        string $subscriberId,
        string $eventClass,
        string $subscriberClass,
        string $methodName,
        array $arguments,
        ArgumentResolverContext $context,
    ): array {
        $resolvers = $this->resolversFor($subscriberId, $eventClass, $subscriberClass, $methodName, $arguments);

        $resolved = [];

        foreach ($arguments as $position => $argument) {
            $resolved[] = $resolvers[$position]->resolve($argument, $context);
        }

        return $resolved;
    }

    /**
     * @param class-string           $eventClass
     * @param class-string           $subscriberClass
     * @param list<ArgumentMetadata> $arguments
     *
     * @return list<ArgumentResolver>
     */
    private function resolversFor(
        string $subscriberId,
        string $eventClass,
        string $subscriberClass,
        string $methodName,
        array $arguments,
    ): array {
        if (isset($this->resolverCache[$subscriberId][$eventClass][$methodName])) {
            return $this->resolverCache[$subscriberId][$eventClass][$methodName];
        }

        $resolvers = [];

        foreach ($arguments as $argument) {
            $resolvers[] = $this->resolverFor($argument, $eventClass, $subscriberClass, $methodName);
        }

        return $this->resolverCache[$subscriberId][$eventClass][$methodName] = $resolvers;
    }

    /**
     * @param class-string $eventClass
     * @param class-string $subscriberClass
     */
    private function resolverFor(
        ArgumentMetadata $argument,
        string $eventClass,
        string $subscriberClass,
        string $methodName,
    ): ArgumentResolver {
        foreach ($this->argumentResolvers as $resolver) {
            if ($resolver->support($argument, $eventClass)) {
                return $resolver;
            }
        }

        throw new NoSuitableResolver($subscriberClass, $methodName, $argument->name);
    }
}
