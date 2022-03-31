<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer;

use function sprintf;

final class EventClassNotRegistered extends SerializeException
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
