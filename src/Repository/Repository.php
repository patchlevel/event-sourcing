<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Identifier\Identifier;

/** @template T of AggregateRoot */
interface Repository
{
    /**
     * @return T
     *
     * @throws AggregateNotFound
     */
    public function load(Identifier $id): AggregateRoot;

    public function has(Identifier $id): bool;

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
