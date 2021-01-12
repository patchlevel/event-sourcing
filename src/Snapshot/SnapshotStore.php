<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Snapshot;

use Patchlevel\EventSourcing\Aggregate\SnapshotableAggregateRoot;

interface SnapshotStore
{
    public function save(Snapshot $snapshot): void;

    /**
     * @param class-string<SnapshotableAggregateRoot> $aggregate
     *
     * @throws SnapshotNotFound
     */
    public function load(string $aggregate, string $id): Snapshot;
}
