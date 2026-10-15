<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber;

use Closure;
use Patchlevel\EventSourcing\Attribute\Partition;
use Patchlevel\EventSourcing\Attribute\PartitionKey;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\NameChanged;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\ProfileCreated;
use RuntimeException;

use function in_array;

#[Projector(self::SUBSCRIBER_ID)]
final class PartitionedProfileProjection
{
    public const SUBSCRIBER_ID = 'partitioned_profile';

    /** @var array<string, list<string>> profile id => processed names in processing order */
    public array $processed = [];

    public int $handled = 0;

    /** @var array<string, string> profile id => partition key that processed its creation */
    public array $partitionKeys = [];

    /** @var list<string> names that throw while processing */
    public array $failingNames = [];

    /** @var Closure(Message):string */
    public Closure $partitionKey;

    public function __construct()
    {
        $this->partitionKey = static fn (Message $message): string => $message->header(StreamNameHeader::class)->streamName;
    }

    #[Partition]
    public function partition(Message $message): string
    {
        return ($this->partitionKey)($message);
    }

    #[Subscribe(ProfileCreated::class)]
    public function handleProfileCreated(ProfileCreated $event, #[PartitionKey]
    string $partitionKey,): void
    {
        $this->partitionKeys[$event->profileId->toString()] = $partitionKey;
        $this->record($event->profileId->toString(), $event->name);
    }

    #[Subscribe(NameChanged::class)]
    public function handleNameChanged(NameChanged $event): void
    {
        $this->record($event->profileId->toString(), $event->name);
    }

    private function record(string $profileId, string $name): void
    {
        $this->handled++;

        if (in_array($name, $this->failingNames, true)) {
            throw new RuntimeException('Forced failure for ' . $name);
        }

        $this->processed[$profileId][] = $name;
    }
}
