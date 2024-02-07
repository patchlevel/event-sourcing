<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus\Serializer;

use RuntimeException;

use function get_debug_type;
use function sprintf;

final class DeserializeFailed extends RuntimeException
{
    public static function decodeFailed(): self
    {
        return new self('Error while decoding message');
    }

    public static function invalidData(mixed $value): self
    {
        return new self(
            sprintf(
                'Invalid data: %s',
                get_debug_type($value),
            ),
        );
    }
}
