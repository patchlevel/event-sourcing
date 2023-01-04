<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Snapshot;

use Patchlevel\EventSourcing\Aggregate\AggregateRootInterface;
use Patchlevel\EventSourcing\Snapshot\Adapter\SnapshotNotFound;

interface SnapshotStore
{
    public function save(AggregateRootInterface $aggregateRoot): void;

    /**
     * @param class-string<T> $aggregateClass
     *
     * @return T
     *
     * @throws SnapshotNotFound
     *
     * @template T of AggregateRootInterface
     */
    public function load(string $aggregateClass, string $id): AggregateRootInterface;
}
