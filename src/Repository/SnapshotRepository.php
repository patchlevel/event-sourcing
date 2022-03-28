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

final class SnapshotRepository implements Repository
{
    private Store $store;
    private EventBus $eventStream;

    /** @var class-string<SnapshotableAggregateRoot> */
    private string $aggregateClass;

    /** @var array<string, SnapshotableAggregateRoot> */
    private array $instances = [];

    private SnapshotStore $snapshotStore;

    /**
     * @param class-string $aggregateClass
     */
    public function __construct(
        Store $store,
        EventBus $eventStream,
        string $aggregateClass,
        SnapshotStore $snapshotStore
    ) {
        if (!is_subclass_of($aggregateClass, SnapshotableAggregateRoot::class)) {
            throw InvalidAggregateClass::notSnapshotableAggregateRoot($aggregateClass);
        }

        $this->store = $store;
        $this->eventStream = $eventStream;
        $this->aggregateClass = $aggregateClass;
        $this->snapshotStore = $snapshotStore;
    }

    public function load(string $id): SnapshotableAggregateRoot
    {
        if (array_key_exists($id, $this->instances)) {
            return $this->instances[$id];
        }

        $aggregateClass = $this->aggregateClass;

        try {
            $snapshot = $this->snapshotStore->load($aggregateClass, $id);
            $messages = $this->store->load($aggregateClass, $id, $snapshot->playhead());

            return $this->instances[$id] = $aggregateClass::createFromSnapshot(
                $snapshot,
                $messages
            );
        } catch (SnapshotNotFound $exception) {
            // do normal workflow
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

        foreach ($messages as $message) {
            $this->eventStream->dispatch($message);
        }

        $snapshot = $aggregate->toSnapshot();
        $this->snapshotStore->save($snapshot);
    }
}
