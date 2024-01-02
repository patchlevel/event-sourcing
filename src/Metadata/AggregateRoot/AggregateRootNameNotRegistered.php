<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\AggregateRoot;

use Patchlevel\EventSourcing\Metadata\MetadataException;

use function sprintf;

final class AggregateRootNameNotRegistered extends MetadataException
{
    public function __construct(string $name)
    {
        parent::__construct(
            sprintf(
                'Aggregate root name "%s" is not registered',
                $name,
            ),
        );
    }
}
