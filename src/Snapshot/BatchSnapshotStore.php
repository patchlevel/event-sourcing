<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Snapshot;

use function sprintf;

final class BatchSnapshotStore implements SnapshotStore
{
    private array $playheadCache;
    private SnapshotStore $wrappedStore;
    private int $batchSize;

    public function __construct(SnapshotStore $wrappedStore, int $batchSize = 10)
    {
        $this->playheadCache = [];
        $this->wrappedStore = $wrappedStore;
        $this->batchSize = $batchSize;
    }

    public function save(Snapshot $snapshot): void
    {
        $key = $this->key($snapshot->aggregate(), $snapshot->id());
        $beforePlayhead = $this->playheadCache[$key] ?? 0;

        $diff = $snapshot->playhead() - $beforePlayhead;

        if ($diff >= $this->batchSize) {
            $this->wrappedStore->save($snapshot);
        }
    }

    public function load(string $aggregate, string $id): Snapshot
    {
        $snapshot = $this->wrappedStore->load($aggregate, $id);

        $this->playheadCache[$this->key($aggregate, $id)] = $snapshot->playhead();

        return $snapshot;
    }

    public function freeMemory(): void
    {
        $this->playheadCache = [];
    }

    private function key(string $aggregate, string $id): string
    {
        return sprintf('%s-%s', $aggregate, $id);
    }
}
