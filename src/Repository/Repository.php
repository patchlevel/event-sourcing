<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Aggregate\AggregateRootId;

/** @template T of AggregateRoot */
interface Repository
{
    /**
     * @return T
     *
     * @throws AggregateNotFound
     */
    public function load(AggregateRootId $id): AggregateRoot;

    public function has(AggregateRootId $id): bool;

    /**
     * @param T $aggregate
     *
     * @throws WrongAggregate
     * @throws AggregateDetached
     * @throws AggregateUnknown
     * @throws PlayheadMismatch
     * @throws AggregateAlreadyExists
     * @throws AggregateOutdated
     */
    public function save(AggregateRoot $aggregate): void;
}
