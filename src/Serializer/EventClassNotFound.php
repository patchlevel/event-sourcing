<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer;

use function sprintf;

final class EventClassNotFound extends SerializeException
{
    public function __construct(string $evenName, string $eventClass)
    {
        parent::__construct(
            sprintf(
                'event class "%s" not found for "%s" event',
                $eventClass,
                $evenName
            ),
        );
    }
}
