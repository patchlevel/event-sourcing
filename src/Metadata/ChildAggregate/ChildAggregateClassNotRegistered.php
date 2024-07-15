<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\ChildAggregate;

use Patchlevel\EventSourcing\Metadata\MetadataException;

use function sprintf;

final class ChildAggregateClassNotRegistered extends MetadataException
{
    public function __construct(string $childAggregate)
    {
        parent::__construct(
            sprintf(
                'Child aggregate class "%s" is not registered',
                $childAggregate,
            ),
        );
    }
}
