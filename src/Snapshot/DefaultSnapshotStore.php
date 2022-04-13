<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Snapshot;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Snapshot\Adapter\SnapshotAdapter;
use Throwable;

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

        $adapter = $this->getAdapter($aggregateClass);

        $adapter->save(
            $key,
            $snapshot->playhead(),
            $snapshot->payload()
        );
    }

    /**
     * @param class-string<AggregateRoot> $aggregateClass
     *
     * @throws SnapshotNotFound
     */
    public function load(string $aggregateClass, string $id): Snapshot
    {
        $adapter = $this->getAdapter($aggregateClass);
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
    private function getAdapter(string $aggregateClass): SnapshotAdapter
    {
        $metadata = $aggregateClass::metadata($aggregateClass);

        $snapshotName = $metadata->snapshotStore;

        if (!$snapshotName) {
            throw new SnapshotNotConfigured($aggregateClass);
        }

        return $this->snapshotAdapters[$snapshotName];
    }

    /**
     * @param class-string<AggregateRoot> $aggregateClass
     */
    private function key(string $aggregateClass, string $aggregateId): string
    {
        $aggregateName = $aggregateClass::metadata($aggregateClass)->name;

        return sprintf('%s-%s', $aggregateName, $aggregateId);
    }

    private function shouldBeSaved(Snapshot $snapshot, string $key): bool
    {
        $aggregateClass = $snapshot->aggregate();
        $batchSize = $aggregateClass::metadata($snapshot->aggregate())->snapshotBatch;

        if (!$batchSize) {
            return true;
        }

        $beforePlayhead = $this->playheadCache[$key] ?? 0;

        $diff = $snapshot->playhead() - $beforePlayhead;

        if ($diff < $batchSize) {
            return false;
        }

        $this->playheadCache[$key] = $snapshot->playhead();

        return true;
    }
}
