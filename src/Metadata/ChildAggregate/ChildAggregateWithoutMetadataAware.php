<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\ChildAggregate;

use Patchlevel\EventSourcing\Metadata\MetadataException;

use function sprintf;

final class ChildAggregateWithoutMetadataAware extends MetadataException
{
    /** @param class-string $childAggregate */
    public function __construct(string $childAggregate)
    {
        parent::__construct(sprintf('The class "%s" does not implements ChildAggregateMetadataAware', $childAggregate));
    }
}
