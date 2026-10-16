<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber;

use Patchlevel\EventSourcing\Attribute\Partition;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Subscription\Partitioner\StreamPartitioner;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\ProfileCreated;

#[Subscriber(self::SUBSCRIBER_ID, RunMode::Once)]
#[Partition(new StreamPartitioner())]
final class OncePartitionedProjection
{
    public const SUBSCRIBER_ID = 'once_profile';

    public int $handled = 0;

    #[Subscribe(ProfileCreated::class)]
    public function handleProfileCreated(ProfileCreated $event): void
    {
        $this->handled++;
    }
}
