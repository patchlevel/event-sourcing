<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Snapshot;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Snapshot\Adapter\SnapshotAdapter;
use Throwable;

use function array_key_exists;
use function sprintf;

final class DefaultSnapshotStore implements SnapshotStore
{
    /** @var array<string, SnapshotAdapter> */
    private array $snapshotAdapters;

    /** @var array<string, int> */
    private array $playheadCache = [];

    /**
     * @param array<string, SnapshotAdapter> $snapshotAdapters
     */
    public function __construct(array $snapshotAdapters)
    {
        $this->snapshotAdapters = $snapshotAdapters;
    }

    public function save(Snapshot $snapshot): void
    {
        $aggregateClass = $snapshot->aggregate();
        $key = $this->key($aggregateClass, $snapshot->id());

        if (!$this->shouldBeSaved($snapshot, $key)) {
            return;
        }

        $adapter = $this->adapter($aggregateClass);

        $adapter->save(
            $key,
            $snapshot->playhead(),
            $snapshot->payload()
        );

        $this->playheadCache[$key] = $snapshot->playhead();
    }

    /**
     * @param class-string<AggregateRoot> $aggregateClass
     *
     * @throws SnapshotNotFound
     */
    public function load(string $aggregateClass, string $id): Snapshot
    {
        $adapter = $this->adapter($aggregateClass);
        $key = $this->key($aggregateClass, $id);

        try {
            [$playhead, $payload] = $adapter->load($key);
        } catch (Throwable $exception) {
            throw new SnapshotNotFound($aggregateClass, $id, $exception);
        }

        $this->playheadCache[$key] = $playhead;

        return new Snapshot(
            $aggregateClass,
            $id,
            $playhead,
            $payload
        );
    }

    public function freeMemory(): void
    {
        $this->playheadCache = [];
    }

    /**
     * @param class-string<AggregateRoot> $aggregateClass
     */
    public function adapter(string $aggregateClass): SnapshotAdapter
    {
        $adapterName = $aggregateClass::metadata()->snapshotStore;

        if (!$adapterName) {
            throw new SnapshotNotConfigured($aggregateClass);
        }

        if (!array_key_exists($adapterName, $this->snapshotAdapters)) {
            throw new AdapterNotFound($adapterName);
        }

        return $this->snapshotAdapters[$adapterName];
    }

    /**
     * @param class-string<AggregateRoot> $aggregateClass
     */
    private function key(string $aggregateClass, string $aggregateId): string
    {
        $aggregateName = $aggregateClass::metadata()->name;

        return sprintf('%s-%s', $aggregateName, $aggregateId);
    }

    private function shouldBeSaved(Snapshot $snapshot, string $key): bool
    {
        $aggregateClass = $snapshot->aggregate();
        $batchSize = $aggregateClass::metadata()->snapshotBatch;

        if (!$batchSize) {
            return true;
        }

        $beforePlayhead = $this->playheadCache[$key] ?? 0;

        $diff = $snapshot->playhead() - $beforePlayhead;

        return $diff >= $batchSize;
    }
}
