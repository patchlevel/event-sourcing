<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Aggregate\SnapshotableAggregateRoot;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\Snapshot\SnapshotNotFound;
use Patchlevel\EventSourcing\Snapshot\SnapshotStore;
use Patchlevel\EventSourcing\Store\Store;

use function array_key_exists;
use function count;
use function is_subclass_of;

final class DefaultRepository implements Repository
{
    private Store $store;
    private EventBus $eventBus;

    /** @var class-string<AggregateRoot> */
    private string $aggregateClass;

    /** @var array<string, AggregateRoot> */
    private array $instances = [];

    private ?SnapshotStore $snapshotStore;

    /**
     * @param class-string<AggregateRoot> $aggregateClass
     */
    public function __construct(
        Store $store,
        EventBus $eventBus,
        string $aggregateClass,
        ?SnapshotStore $snapshotStore = null
    ) {
        $this->store = $store;
        $this->eventBus = $eventBus;
        $this->aggregateClass = $aggregateClass;
        $this->snapshotStore = $snapshotStore;
    }

    public function load(string $id): AggregateRoot
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        $aggregateClass = $this->aggregateClass;

        if ($this->snapshotStore && is_subclass_of($aggregateClass, SnapshotableAggregateRoot::class)) {
            try {
                $snapshot = $this->snapshotStore->load($aggregateClass, $id);
                $messages = $this->store->load($aggregateClass, $id, $snapshot->playhead());

                return $this->instances[$id] = $aggregateClass::createFromSnapshot(
                    $snapshot,
                    $messages
                );
            } catch (SnapshotNotFound) {
                // do normal workflow
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

        if (!$this->snapshotStore || !($aggregate instanceof SnapshotableAggregateRoot)) {
            return;
        }

        $snapshot = $aggregate->toSnapshot();
        $this->snapshotStore->save($snapshot);
    }
}
