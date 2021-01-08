<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

interface Store
{
    /**
     * @param class-string $aggregate
     *
     * @return AggregateChanged[]
     */
    public function load(string $aggregate, string $id): array;

    /**
     * @param class-string $aggregate
     */
    public function has(string $aggregate, string $id): bool;

    /**
     * @param class-string       $aggregate
     * @param AggregateChanged[] $events
     */
    public function saveBatch(string $aggregate, string $id, array $events): void;

    public function prepare(): void;

    public function drop(): void;
}
