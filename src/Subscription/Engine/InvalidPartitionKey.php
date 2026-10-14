<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use RuntimeException;

use function get_debug_type;
use function sprintf;

final class InvalidPartitionKey extends RuntimeException
{
    public static function notAString(string $subscriberId, mixed $partitionKey): self
    {
        return new self(sprintf(
            'The partition method of the subscriber "%s" must return a string, "%s" given.',
            $subscriberId,
            get_debug_type($partitionKey),
        ));
    }

    public static function empty(string $subscriberId): self
    {
        return new self(sprintf(
            'The partition method of the subscriber "%s" returned an empty partition key.',
            $subscriberId,
        ));
    }

    public static function tooLong(string $subscriberId, string $partitionKey, int $maxLength): self
    {
        return new self(sprintf(
            'The partition key "%s" of the subscriber "%s" is too long, the partition id must not exceed %d bytes.',
            $partitionKey,
            $subscriberId,
            $maxLength,
        ));
    }
}
