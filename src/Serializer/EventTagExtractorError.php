<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Serializer;

use RuntimeException;

use function get_debug_type;
use function sprintf;

final class EventTagExtractorError extends RuntimeException
{
    /** @param class-string $class */
    public static function invalidValueType(string $class, string $property, mixed $value): self
    {
        return new self(
            sprintf(
                'Event tag value for property "%s" in class "%s" must be stringable, %s given',
                $property,
                $class,
                get_debug_type($value),
            ),
        );
    }
}
