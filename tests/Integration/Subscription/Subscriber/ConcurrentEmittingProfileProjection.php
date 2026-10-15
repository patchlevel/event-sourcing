<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Attribute\Partition;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Subscription\Subscriber\EventEmitter\EventEmitter;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\NameChanged;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\NotificationSent;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\ProfileId;

/** Like ConcurrentProfileProjection, but every processed message also emits an event into the subscriber stream. */
#[Projector(self::SUBSCRIBER_ID)]
final class ConcurrentEmittingProfileProjection
{
    public const SUBSCRIBER_ID = 'concurrent_emitting_profile';

    public function __construct(
        private readonly Connection $connection,
        private readonly string $worker,
    ) {
    }

    #[Partition]
    public function partition(Message $message): string
    {
        return $message->header(StreamNameHeader::class)->streamName;
    }

    #[Subscribe(ProfileCreated::class)]
    public function handleProfileCreated(ProfileCreated $event, EventEmitter $emitter): void
    {
        $this->record($event->profileId, $event->name, $emitter);
    }

    #[Subscribe(NameChanged::class)]
    public function handleNameChanged(NameChanged $event, EventEmitter $emitter): void
    {
        $this->record($event->profileId, $event->name, $emitter);
    }

    private function record(ProfileId $profileId, string $name, EventEmitter $emitter): void
    {
        $this->connection->insert(ConcurrentProfileProjection::TABLE, [
            'profile_id' => $profileId->toString(),
            'name' => $name,
            'worker' => $this->worker,
        ]);

        $emitter->emit([new NotificationSent($profileId)]);
    }
}
