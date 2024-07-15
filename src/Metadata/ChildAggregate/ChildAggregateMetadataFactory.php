<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\ChildAggregate;

use Patchlevel\EventSourcing\Aggregate\ChildAggregate;

interface ChildAggregateMetadataFactory
{
    /**
     * @param class-string<T> $aggregate
     *
     * @return ChildAggregateMetadata<T>
     *
     * @template T of ChildAggregate
     */
    public function metadata(string $aggregate): ChildAggregateMetadata;
}
