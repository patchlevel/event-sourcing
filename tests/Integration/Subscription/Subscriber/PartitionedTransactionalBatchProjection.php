<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
use Patchlevel\EventSourcing\Attribute\BatchBegin;
use Patchlevel\EventSourcing\Attribute\BatchFlush;
use Patchlevel\EventSourcing\Attribute\BatchRollback;
use Patchlevel\EventSourcing\Attribute\BatchState;
use Patchlevel\EventSourcing\Attribute\Partition;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Subscription\Partitioner\StreamPartitioner;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\NameChanged;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\ProfileCreated;
use RuntimeException;
use stdClass;

/**
 * One partition per profile. Every batch is a database transaction on the shared connection,
 * so overlapping batches of different partitions would roll back the wrong writes.
 */
#[Projector(self::SUBSCRIBER_ID)]
#[Partition(new StreamPartitioner())]
final class PartitionedTransactionalBatchProjection
{
    public const SUBSCRIBER_ID = 'partitioned_transactional_batch';
    public const POISON = 'POISON';

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    #[Setup]
    public function create(): void
    {
        $table = new Table(self::SUBSCRIBER_ID);
        $table->addColumn('id', 'string')->setLength(36);
        $table->addColumn('name', 'string')->setLength(255);
        $table->setPrimaryKey(['id']);

        $this->connection->createSchemaManager()->createTable($table);
    }

    #[BatchBegin]
    public function begin(): stdClass
    {
        $this->connection->beginTransaction();

        return new stdClass();
    }

    #[Subscribe(ProfileCreated::class)]
    public function onProfileCreated(ProfileCreated $event, #[BatchState]
    stdClass $state,): void
    {
        $this->connection->insert(self::SUBSCRIBER_ID, ['id' => $event->profileId->toString(), 'name' => $event->name]);
    }

    #[Subscribe(NameChanged::class)]
    public function onNameChanged(NameChanged $event, #[BatchState]
    stdClass $state,): void
    {
        if ($event->name === self::POISON) {
            throw new RuntimeException('poisoned event');
        }

        $this->connection->update(self::SUBSCRIBER_ID, ['name' => $event->name], ['id' => $event->profileId->toString()]);
    }

    #[BatchFlush]
    public function flush(stdClass $state): void
    {
        $this->connection->commit();
    }

    #[BatchRollback]
    public function rollback(stdClass $state): void
    {
        $this->connection->rollBack();
    }
}
