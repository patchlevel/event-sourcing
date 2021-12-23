<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;

/**
 * @template T of AggregateRoot
 */
interface Repository
{
    /**
     * @return T
     */
    public function load(string $id): AggregateRoot;

    public function has(string $id): bool;

    /**
     * @param T $aggregate
     */
    public function save(AggregateRoot $aggregate): void;
}
