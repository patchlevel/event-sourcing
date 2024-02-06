<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus\Serializer;

use Patchlevel\EventSourcing\EventBus\Message;
use RuntimeException;

use function get_debug_type;
use function sprintf;

final class DeserializeFailed extends RuntimeException
{
    public static function decodeFailed(): self
    {
        return new self('Error while decoding message');
    }

    public static function invalidMessage(mixed $value): self
    {
        return new self(
            sprintf(
                'Value should me an instance of %s, but is %s',
                Message::class,
                get_debug_type($value),
            ),
        );
    }
}
