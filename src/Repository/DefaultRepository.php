<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootMetadata;
use Patchlevel\EventSourcing\Snapshot\SnapshotNotFound;
use Patchlevel\EventSourcing\Snapshot\SnapshotStore;
use Patchlevel\EventSourcing\Store\Store;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

use function assert;
use function count;
use function sprintf;

/**
 * @template T of AggregateRoot
 * @implements Repository<T>
 */
final class DefaultRepository implements Repository
{
    private Store $store;
    private EventBus $eventBus;

    /** @var class-string<T> */
    private string $aggregateClass;

    private ?SnapshotStore $snapshotStore;
    private LoggerInterface $logger;

    private AggregateRootMetadata $metadata;

    /**
     * @param class-string<T> $aggregateClass
     */
    public function __construct(
        Store $store,
        EventBus $eventBus,
        string $aggregateClass,
        ?SnapshotStore $snapshotStore = null,
        ?LoggerInterface $logger = null
    ) {
        $this->store = $store;
        $this->eventBus = $eventBus;
        $this->aggregateClass = $aggregateClass;
        $this->snapshotStore = $snapshotStore;
        $this->logger = $logger ?? new NullLogger();
        $this->metadata = $aggregateClass::metadata();
    }

    /**
     * @return T
     */
    public function load(string $id): AggregateRoot
    {
        $aggregateClass = $this->aggregateClass;

        if ($this->snapshotStore && $this->metadata->snapshotStore) {
            try {
                return $this->loadFromSnapshot($aggregateClass, $id);
            } catch (SnapshotRebuildFailed $exception) {
                $this->logger->error($exception->getMessage());
            } catch (SnapshotNotFound) {
                $this->logger->debug(
                    sprintf(
                        'snapshot for aggregate "%s" with the id "%s" not found',
                        $aggregateClass,
                        $id
                    )
                );
            }
        }

        $messages = $this->store->load($aggregateClass, $id);

        if (count($messages) === 0) {
            throw new AggregateNotFound($aggregateClass, $id);
        }

        return $aggregateClass::createFromMessages($messages);
    }

    public function has(string $id): bool
    {
        return $this->store->has($this->aggregateClass, $id);
    }

    /**
     * @param T $aggregate
     */
    public function save(AggregateRoot $aggregate): void
    {
        $this->assertRightAggregate($aggregate);

        $messages = $aggregate->releaseMessages();

        if (count($messages) === 0) {
            return;
        }

        $this->store->save(...$messages);
        $this->eventBus->dispatch(...$messages);
    }

    /**
     * @param class-string<T> $aggregateClass
     *
     * @return T
     */
    private function loadFromSnapshot(string $aggregateClass, string $id): AggregateRoot
    {
        assert($this->snapshotStore instanceof SnapshotStore);

        $aggregate = $this->snapshotStore->load($aggregateClass, $id);
        $messages = $this->store->load($aggregateClass, $id, $aggregate->playhead());

        if ($messages === []) {
            return $aggregate;
        }

        try {
            $aggregate->catchUp($messages);
        } catch (Throwable $exception) {
            throw new SnapshotRebuildFailed($aggregateClass, $id, $exception);
        }

        $batchSize = $this->metadata->snapshotBatch ?: 1;

        if (count($messages) >= $batchSize) {
            $this->snapshotStore->save($aggregate);
        }

        return $aggregate;
    }

    private function assertRightAggregate(AggregateRoot $aggregate): void
    {
        if (!$aggregate instanceof $this->aggregateClass) {
            throw new WrongAggregate($aggregate::class, $this->aggregateClass);
        }
    }
}
