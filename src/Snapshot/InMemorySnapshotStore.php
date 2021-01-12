<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Snapshot;

use function array_key_exists;
use function sprintf;

class InMemorySnapshotStore implements SnapshotStore
{
    /** @var array<string, Snapshot> */
    private array $snapshots = [];

    public function save(Snapshot $snapshot): void
    {
        $key = $this->key($snapshot->aggregate(), $snapshot->id());
        $this->snapshots[$key] = $snapshot;
    }

    public function load(string $aggregate, string $id): Snapshot
    {
        $key = $this->key($aggregate, $id);

        if (!array_key_exists($key, $this->snapshots)) {
            throw new SnapshotNotFound($aggregate, $id);
        }

        return $this->snapshots[$key];
    }

    private function key(string $aggregate, string $id): string
    {
        return sprintf('%s-%s', $aggregate, $id);
    }
}
