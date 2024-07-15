<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\ChildAggregate;


use Patchlevel\EventSourcing\Aggregate\ChildAggregate;
use Patchlevel\EventSourcing\Aggregate\ChildAggregateMetadataAware;
use function is_a;

final class ChildAggregateMetadataAwareMetadataFactory implements ChildAggregateMetadataFactory
{
    /**
     * @param class-string<T> $aggregate
     *
     * @return ChildAggregateMetadata<T>
     *
     * @template T of ChildAggregate
     */
    public function metadata(string $aggregate): ChildAggregateMetadata
    {
        if (!is_a($aggregate, ChildAggregateMetadataAware::class, true)) {
            throw new ChildAggregateWithoutMetadataAware($aggregate);
        }

        return $aggregate::metadata();
    }
}
