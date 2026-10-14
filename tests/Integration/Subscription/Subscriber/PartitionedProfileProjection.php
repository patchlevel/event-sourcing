<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber;

use Patchlevel\EventSourcing\Attribute\Partition;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\NameChanged;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\ProfileCreated;
use RuntimeException;

use function in_array;

#[Projector('partitioned_profile')]
final class PartitionedProfileProjection
{
    /** @var array<string, list<string>> profile id => ordered list of processed values */
    public array $processed = [];

    /** @var list<string> profile ids that must throw while processing */
    public array $failing = [];

    /** @var array<string, true> partition keys whose next resolution must throw once */
    public array $failingPartitionKeys = [];

    #[Partition(shards: 2)]
    public function partition(Message $message): string
    {
        $partitionKey = $message->header(StreamNameHeader::class)->streamName;

        if (isset($this->failingPartitionKeys[$partitionKey])) {
            unset($this->failingPartitionKeys[$partitionKey]);

            throw new RuntimeException('Forced partition failure for ' . $partitionKey);
        }

        return $partitionKey;
    }

    public static function partitionKey(string $profileId): string
    {
        return 'profile-' . $profileId;
    }

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

    private function record(string $profileId, string $value): void
    {
        if (in_array($profileId, $this->failing, true)) {
            throw new RuntimeException('Forced failure for profile ' . $profileId);
        }

        $this->processed[$profileId][] = $value;
    }
}
