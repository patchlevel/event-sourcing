<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber;

use Patchlevel\EventSourcing\Attribute\Partition;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Subscription\Partitioner\StreamPartitioner;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\NameChanged;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\ProfileCreated;
use RuntimeException;

use function in_array;

/** The partition is set on the class with a partitioner. */
#[Projector(self::SUBSCRIBER_ID)]
#[Partition(new StreamPartitioner(), removeIdleAfter: 60)]
final class EphemeralPartitionProjection
{
    public const SUBSCRIBER_ID = 'ephemeral_profile';

    /** @var array<string, list<string>> profile id => processed names */
    public array $processed = [];

    /** @var list<string> */
    public array $failingNames = [];

    #[Subscribe(ProfileCreated::class)]
    public function handleProfileCreated(ProfileCreated $event): void
    {
        $this->record($event->profileId->toString(), $event->name);
    }

    #[Subscribe(NameChanged::class)]
    public function handleNameChanged(NameChanged $event): void
    {
        $this->record($event->profileId->toString(), $event->name);
    }

    private function record(string $profileId, string $name): void
    {
        if (in_array($name, $this->failingNames, true)) {
            throw new RuntimeException('Forced failure for ' . $name);
        }

        $this->processed[$profileId][] = $name;
    }
}
