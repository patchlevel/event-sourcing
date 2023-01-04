<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use Patchlevel\EventSourcing\Aggregate\AggregateRootInterface;

/**
 * @template T of AggregateRootInterface
 */
interface Repository
{
    /**
     * @return T
     */
    public function load(string $id): AggregateRootInterface;

    public function has(string $id): bool;

    /**
     * @param T $aggregate
     */
    public function save(AggregateRootInterface $aggregate): void;
}
