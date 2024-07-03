<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Event;

use Patchlevel\EventSourcing\Metadata\MetadataException;

use function sprintf;

final class EventAlreadyInRegistry extends MetadataException
{
    public function __construct(string $eventName)
    {
        parent::__construct(sprintf(
            'The event name "%s" is already used in the registry. Maybe you defined 2 events with the same name.',
            $eventName,
        ));
    }
}
