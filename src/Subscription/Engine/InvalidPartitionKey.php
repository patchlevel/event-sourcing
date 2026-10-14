<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Patchlevel\EventSourcing\Subscription\Subscription;
use RuntimeException;

use function get_debug_type;
use function sprintf;

final class InvalidPartitionKey extends RuntimeException
{
    public static function notAString(string $subscriberId, mixed $key): self
    {
        return new self(
            sprintf(
                'The partition method of the subscriber "%s" must return a string, "%s" given.',
                $subscriberId,
                get_debug_type($key),
            ),
        );
    }

    public static function empty(string $subscriberId): self
    {
        return new self(
            sprintf(
                'The partition method of the subscriber "%s" returned an empty partition key.',
                $subscriberId,
            ),
        );
    }

    public static function containsSeparator(string $subscriberId, string $key): self
    {
        return new self(
            sprintf(
                'The partition key "%s" of the subscriber "%s" must not contain "%s".',
                $key,
                $subscriberId,
                Subscription::PARTITION_SEPARATOR,
            ),
        );
    }

    public static function tooLong(string $subscriberId, string $key): self
    {
        return new self(
            sprintf(
                'The partition key "%s" of the subscriber "%s" is too long, the partition id must not exceed %d bytes.',
                $key,
                $subscriberId,
                Subscription::MAX_ID_LENGTH,
            ),
        );
    }
}
