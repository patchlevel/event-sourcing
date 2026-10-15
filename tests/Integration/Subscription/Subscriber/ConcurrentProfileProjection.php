<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Attribute\Partition;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\NameChanged;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\ProfileCreated;

/** Writes every processed message with the name of the worker, in the transaction of the subscription. */
#[Projector(self::SUBSCRIBER_ID)]
final class ConcurrentProfileProjection
{
    public const SUBSCRIBER_ID = 'concurrent_profile';
    public const TABLE = 'concurrent_profile';

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
        $this->connection->insert(self::TABLE, [
            'profile_id' => $profileId,
            'name' => $name,
            'worker' => $this->worker,
        ]);
    }
}
