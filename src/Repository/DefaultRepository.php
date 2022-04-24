<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\Snapshot\SnapshotNotFound;
use Patchlevel\EventSourcing\Snapshot\SnapshotStore;
use Patchlevel\EventSourcing\Store\Store;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

use function array_key_exists;
use function assert;
use function count;
use function sprintf;

final class DefaultRepository implements Repository
{
    private Store $store;
    private EventBus $eventBus;

    /** @var class-string<AggregateRoot> */
    private string $aggregateClass;

    /** @var array<string, AggregateRoot> */
    private array $instances = [];

    private ?SnapshotStore $snapshotStore;
    private LoggerInterface $logger;

    private ?bool $snapshotConfigured = null;

    /**
     * @param class-string<AggregateRoot> $aggregateClass
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
    }

    public function load(string $id): AggregateRoot
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        $aggregateClass = $this->aggregateClass;

        if ($this->snapshotStore && $this->snapshotConfigured()) {
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

        return $this->instances[$id] = $aggregateClass::createFromMessages($messages);
    }

    public function has(string $id): bool
    {
        if (array_key_exists($id, $this->instances)) {
            return true;
        }

        return $this->store->has($this->aggregateClass, $id);
    }

    public function save(AggregateRoot $aggregate): void
    {
        if (!$aggregate instanceof $this->aggregateClass) {
            throw new WrongAggregate($aggregate::class, $this->aggregateClass);
        }

        $messages = $aggregate->releaseMessages();

        if (count($messages) === 0) {
            return;
        }

        $this->store->save(...$messages);
        $this->eventBus->dispatch(...$messages);

        if (!$this->snapshotStore || !$this->snapshotConfigured()) {
            return;
        }

        $this->snapshotStore->save($aggregate);
    }

    /**
     * @param class-string<AggregateRoot> $aggregateClass
     */
    private function loadFromSnapshot(string $aggregateClass, string $id): AggregateRoot
    {
        assert($this->snapshotStore instanceof SnapshotStore);

        $aggregate = $this->snapshotStore->load($aggregateClass, $id);
        $messages = $this->store->load($aggregateClass, $id, $aggregate->playhead());

        try {
            $aggregate->catchUp($messages);

            return $aggregate;
        } catch (Throwable $exception) {
            throw new SnapshotRebuildFailed($aggregateClass, $id, $exception);
        }
    }

    private function snapshotConfigured(): bool
    {
        if ($this->snapshotConfigured !== null) {
            return $this->snapshotConfigured;
        }

        $aggregateClass = $this->aggregateClass;

        return $this->snapshotConfigured = $aggregateClass::metadata()->snapshotStore !== null;
    }
}
