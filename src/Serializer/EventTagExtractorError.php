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

    /** @param class-string $class */
    public static function invalidMethodValueType(string $class, string $method, mixed $value): self
    {
        return new self(
            sprintf(
                'Event tag value returned by method "%s" in class "%s" must be stringable, %s given',
                $method,
                $class,
                get_debug_type($value),
            ),
        );
    }

    /** @param class-string $class */
    public static function methodHasRequiredParameters(string $class, string $method): self
    {
        return new self(
            sprintf(
                'Event tag method "%s" in class "%s" must not have required parameters',
                $method,
                $class,
            ),
        );
    }
}
