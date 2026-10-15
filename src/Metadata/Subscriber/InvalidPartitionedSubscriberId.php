<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Subscriber;

use Patchlevel\EventSourcing\Metadata\MetadataException;
use Patchlevel\EventSourcing\Subscription\Subscription;

use function sprintf;

final class InvalidPartitionedSubscriberId extends MetadataException
{
    /** @param class-string $subscriber */
    public function __construct(string $subscriber, string $id)
    {
        parent::__construct(
            sprintf(
                'The id "%s" of the partitioned subscriber "%s" must not contain "%s", it separates the subscriber id from the partition key.',
                $id,
                $subscriber,
                Subscription::PARTITION_SEPARATOR,
            ),
        );
    }
}
