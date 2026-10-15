<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber;

use ArrayObject;
use Patchlevel\EventSourcing\Attribute\BatchBegin;
use Patchlevel\EventSourcing\Attribute\BatchFlush;
use Patchlevel\EventSourcing\Attribute\BatchState;
use Patchlevel\EventSourcing\Attribute\Partition;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Subscription\Partitioner\StreamPartitioner;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\NameChanged;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\ProfileCreated;
use RuntimeException;

use function in_array;

/** Collects the names of a batch and only stores them on flush. */
#[Projector(self::SUBSCRIBER_ID)]
#[Partition(new StreamPartitioner())]
final class PartitionedBatchProfileProjection
{
    public const SUBSCRIBER_ID = 'partitioned_batch_profile';

    /** @var array<string, list<string>> profile id => flushed names */
    public array $flushed = [];

    public int $flushCount = 0;

    /** @var list<string> names that let the flush fail */
    public array $failingNames = [];

    /** @param ArrayObject<int, array{string, string}> $state */
    #[Subscribe(ProfileCreated::class)]
    public function handleProfileCreated(ProfileCreated $event, #[BatchState]
    ArrayObject $state,): void
    {
        $state[] = [$event->profileId->toString(), $event->name];
    }

    /** @param ArrayObject<int, array{string, string}> $state */
    #[Subscribe(NameChanged::class)]
    public function handleNameChanged(NameChanged $event, #[BatchState]
    ArrayObject $state,): void
    {
        $state[] = [$event->profileId->toString(), $event->name];
    }

    /** @return ArrayObject<int, array{string, string}> */
    #[BatchBegin]
    public function begin(): ArrayObject
    {
        return new ArrayObject();
    }

    /** @param ArrayObject<int, array{string, string}> $state */
    #[BatchFlush(afterMessages: 2)]
    public function flush(ArrayObject $state): void
    {
        $this->flushCount++;

        foreach ($state as [, $name]) {
            if (in_array($name, $this->failingNames, true)) {
                throw new RuntimeException('Forced flush failure for ' . $name);
            }
        }

        foreach ($state as [$profileId, $name]) {
            $this->flushed[$profileId][] = $name;
        }
    }
}
