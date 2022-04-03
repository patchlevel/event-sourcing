<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Event;

use Patchlevel\EventSourcing\Metadata\MetadataException;

use function sprintf;

final class EventNameNotRegistered extends MetadataException
{
    public function __construct(string $evenName)
    {
        parent::__construct(
            sprintf(
                'Event name "%s" is not registered',
                $evenName
            ),
        );
    }
}
