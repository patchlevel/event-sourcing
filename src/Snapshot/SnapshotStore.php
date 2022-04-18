<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Snapshot;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Snapshot\Adapter\SnapshotNotFound;

interface SnapshotStore
{
    public function save(Snapshot $snapshot): void;

    /**
     * @param class-string<AggregateRoot> $aggregateClass
     *
     * @throws SnapshotNotFound
     */
    public function load(string $aggregateClass, string $id): Snapshot;
}
