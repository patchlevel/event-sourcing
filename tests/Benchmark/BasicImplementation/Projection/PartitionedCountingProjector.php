<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Projection;

use Patchlevel\EventSourcing\Attribute\Partition;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events\NameChanged;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events\ProfileCreated;

/** One partition per aggregate, the projection only counts the handled events. */
#[Projector('partitioned_counting')]
final class PartitionedCountingProjector
{
    public int $handled = 0;

    #[Partition]
    public function partition(Message $message): string
    {
        return $message->header(StreamNameHeader::class)->streamName;
    }

    #[Subscribe(ProfileCreated::class)]
    public function onProfileCreated(ProfileCreated $event): void
    {
        $this->handled++;
    }

    #[Subscribe(NameChanged::class)]
    public function onNameChanged(NameChanged $event): void
    {
        $this->handled++;
    }
}
