<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer\Hydrator;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;

interface AggregateRootHydrator
{
    /**
     * @param class-string<T>      $class
     * @param array<string, mixed> $data
     *
     * @return T
     *
     * @template T of AggregateRoot
     */
    public function hydrate(string $class, array $data): AggregateRoot;

    /** @return array<string, mixed> */
    public function extract(AggregateRoot $aggregateRoot): array;
}
