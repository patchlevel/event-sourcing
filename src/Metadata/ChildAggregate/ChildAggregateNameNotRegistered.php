<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\ChildAggregate;

use Patchlevel\EventSourcing\Metadata\MetadataException;

use function sprintf;

final class ChildAggregateNameNotRegistered extends MetadataException
{
    public function __construct(string $name)
    {
        parent::__construct(
            sprintf(
                'Child aggregate name "%s" is not registered',
                $name,
            ),
        );
    }
}
