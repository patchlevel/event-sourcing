<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer;

use function sprintf;

final class EventNameNotRegistered extends SerializeException
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
