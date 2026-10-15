<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber;

use RuntimeException;

use function sprintf;

final class SubscriberIdCollidesWithPartition extends RuntimeException
{
    public function __construct(string $subscriberId, string $partitionedSubscriberId)
    {
        parent::__construct(sprintf(
            'The subscriber id "%s" collides with the partitions of the partitioned subscriber "%s".',
            $subscriberId,
            $partitionedSubscriberId,
        ));
    }
}
