<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer;

use function get_debug_type;
use function sprintf;

final class EventPayloadNotAnArray extends SerializeException
{
    /** @param class-string $eventClass */
    public function __construct(string $eventClass, mixed $payload)
    {
        parent::__construct(
            sprintf(
                'The event "%s" has to be extracted to an array, "%s" given.',
                $eventClass,
                get_debug_type($payload),
            ),
        );
    }
}
