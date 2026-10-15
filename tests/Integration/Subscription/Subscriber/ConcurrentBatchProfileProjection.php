<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Attribute\BatchBegin;
use Patchlevel\EventSourcing\Attribute\BatchFlush;
use Patchlevel\EventSourcing\Attribute\BatchRollback;
use Patchlevel\EventSourcing\Attribute\Partition;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\NameChanged;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\ProfileCreated;

/** Like ConcurrentProfileProjection, but every batch of three messages is its own database transaction. */
#[Projector(self::SUBSCRIBER_ID)]
final class ConcurrentBatchProfileProjection
{
    public const SUBSCRIBER_ID = 'concurrent_batch_profile';

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

    #[BatchBegin]
    public function begin(): void
    {
        $this->connection->beginTransaction();
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

    #[BatchFlush(afterMessages: 3)]
    public function flush(): void
    {
        $this->connection->commit();
    }

    #[BatchRollback]
    public function rollback(): void
    {
        $this->connection->rollBack();
    }

    private function record(string $profileId, string $name): void
    {
        $this->connection->insert(ConcurrentProfileProjection::TABLE, [
            'profile_id' => $profileId,
            'name' => $name,
            'worker' => $this->worker,
        ]);
    }
}
