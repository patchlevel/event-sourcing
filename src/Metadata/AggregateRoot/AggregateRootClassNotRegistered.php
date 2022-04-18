<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\AggregateRoot;

use Patchlevel\EventSourcing\Metadata\MetadataException;

use function sprintf;

final class AggregateRootClassNotRegistered extends MetadataException
{
    public function __construct(string $eventClass)
    {
        parent::__construct(
            sprintf(
                'Aggregate root class "%s" is not registered',
                $eventClass,
            ),
        );
    }
}
