<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\ChildAggregate;

use Patchlevel\EventSourcing\Metadata\MetadataException;

use function sprintf;

final class ChildAggregateAlreadyInRegistry extends MetadataException
{
    public function __construct(string $aggregateName)
    {
        parent::__construct(sprintf(
            'The child aggregate name "%s" is already used in the registry. Maybe you defined 2 aggregates with the same name.',
            $aggregateName,
        ));
    }
}
