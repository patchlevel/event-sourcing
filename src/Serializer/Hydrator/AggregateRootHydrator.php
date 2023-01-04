<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer\Hydrator;

use Patchlevel\EventSourcing\Aggregate\AggregateRootInterface;

interface AggregateRootHydrator
{
    /**
     * @param class-string<T>      $class
     * @param array<string, mixed> $data
     *
     * @return T
     *
     * @template T of AggregateRootInterface
     */
    public function hydrate(string $class, array $data): AggregateRootInterface;

    /**
     * @return array<string, mixed>
     */
    public function extract(AggregateRootInterface $aggregateRoot): array;
}
