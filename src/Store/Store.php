<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;

interface Store
{
    /**
     * @param class-string<AggregateRoot> $aggregate
     *
     * @return AggregateChanged[]
     */
    public function load(string $aggregate, string $id): array;

    /**
     * @param class-string<AggregateRoot> $aggregate
     */
    public function has(string $aggregate, string $id): bool;

    /**
     * @param class-string<AggregateRoot> $aggregate
     * @param AggregateChanged[]          $events
     */
    public function saveBatch(string $aggregate, string $id, array $events): void;

    public function prepare(): void;

    public function drop(): void;
}
