<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Subscriber;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\MetadataException;
use Patchlevel\EventSourcing\Subscription\Subscription;

use function sprintf;

final class InvalidPartitionMethod extends MetadataException
{
    /** @param class-string $subscriber */
    public static function invalidSignature(string $subscriber, string $method): self
    {
        return new self(
            sprintf(
                'The partition method "%s" on the subscriber "%s" must have exactly one parameter of type "%s".',
                $method,
                $subscriber,
                Message::class,
            ),
        );
    }

    /** @param class-string $subscriber */
    public static function invalidSubscriberId(string $subscriber, string $id): self
    {
        return new self(
            sprintf(
                'The id "%s" of the partitioned subscriber "%s" must not contain "%s", it separates the subscriber id from the partition key.',
                $id,
                $subscriber,
                Subscription::PARTITION_SEPARATOR,
            ),
        );
    }
}
