<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Event;

use Patchlevel\EventSourcing\Metadata\MetadataException;

use function sprintf;

final class EventClassNotRegistered extends MetadataException
{
    public function __construct(string $eventClass)
    {
        parent::__construct(
            sprintf(
                'Event class "%s" is not registered',
                $eventClass,
            ),
        );
    }
}
