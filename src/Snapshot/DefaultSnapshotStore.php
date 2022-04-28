<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Snapshot;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Serializer\Hydrator\AggregateRootHydrator;
use Patchlevel\EventSourcing\Serializer\Hydrator\MetadataAggregateRootHydrator;
use Patchlevel\EventSourcing\Snapshot\Adapter\SnapshotAdapter;
use Throwable;

use function array_key_exists;
use function sprintf;

final class DefaultSnapshotStore implements SnapshotStore
{
    /** @var array<string, SnapshotAdapter> */
    private array $snapshotAdapters;

    private AggregateRootHydrator $hydrator;

    /** @var array<string, int> */
    private array $playheadCache = [];

    /**
     * @param array<string, SnapshotAdapter> $snapshotAdapters
     */
    public function __construct(array $snapshotAdapters, ?AggregateRootHydrator $hydrator = null)
    {
        $this->snapshotAdapters = $snapshotAdapters;
        $this->hydrator = $hydrator ?? new MetadataAggregateRootHydrator();
    }

    public function save(AggregateRoot $aggregateRoot): void
    {
        $aggregateClass = $aggregateRoot::class;
        $key = $this->key($aggregateClass, $aggregateRoot->aggregateRootId());

        if (!$this->shouldBeSaved($aggregateRoot, $key)) {
            return;
        }

        $adapter = $this->adapter($aggregateClass);

        $adapter->save(
            $key,
            $this->hydrator->extract($aggregateRoot),
        );

        $this->playheadCache[$key] = $aggregateRoot->playhead();
    }

    /**
     * @param class-string<T> $aggregateClass
     *
     * @return T
     *
     * @throws SnapshotNotFound
     *
     * @template T of AggregateRoot
     */
    public function load(string $aggregateClass, string $id): AggregateRoot
    {
        $adapter = $this->adapter($aggregateClass);
        $key = $this->key($aggregateClass, $id);

        try {
            $data = $adapter->load($key);
        } catch (Throwable $exception) {
            throw new SnapshotNotFound($aggregateClass, $id, $exception);
        }

        $aggregate = $this->hydrator->hydrate($aggregateClass, $data);

        $this->playheadCache[$key] = $aggregate->playhead();

        return $aggregate;
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

    private function shouldBeSaved(AggregateRoot $aggregateRoot, string $key): bool
    {
        $batchSize = $aggregateRoot::metadata()->snapshotBatch;

        if (!$batchSize) {
            return true;
        }

        $beforePlayhead = $this->playheadCache[$key] ?? 0;

        $diff = $aggregateRoot->playhead() - $beforePlayhead;

        return $diff >= $batchSize;
    }
}
