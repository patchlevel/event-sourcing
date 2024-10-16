<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Snapshot;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Aggregate\AggregateRootId;
use Patchlevel\EventSourcing\Snapshot\Adapter\SnapshotNotFound;

interface SnapshotStore
{
    /**
     * @throws SnapshotNotConfigured
     * @throws AdapterNotFound
     */
    public function save(AggregateRoot $aggregateRoot): void;

    /**
     * @param class-string<T> $aggregateClass
     *
     * @return T
     *
     * @throws SnapshotNotFound
     * @throws SnapshotVersionInvalid
     * @throws SnapshotNotConfigured
     * @throws AdapterNotFound
     *
     * @template T of AggregateRoot
     */
    public function load(string $aggregateClass, AggregateRootId $id): AggregateRoot;
}
