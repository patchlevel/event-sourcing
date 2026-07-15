<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
use Patchlevel\EventSourcing\Attribute\BatchBegin;
use Patchlevel\EventSourcing\Attribute\BatchFlush;
use Patchlevel\EventSourcing\Attribute\BatchRollback;
use Patchlevel\EventSourcing\Attribute\BatchState;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\NameChanged;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\ProfileCreated;
use RuntimeException;

/**
 * Writes the projection rows inside a transaction that is opened in the batch begin method and
 * committed in the flush method. When an event fails, the rollback method discards every write
 * made since the batch was started.
 */
#[Projector(self::SUBSCRIBER_ID)]
final class BatchProfileProjection
{
    public const SUBSCRIBER_ID = 'batch_profile';
    public const POISON = 'POISON';

    public int $beginCount = 0;
    public int $flushCount = 0;
    public int $rollbackCount = 0;

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Setup]
    public function create(): void
    {
        $table = new Table($this->tableName());
        $table->addColumn('id', 'string')->setLength(36);
        $table->addColumn('name', 'string')->setLength(255);
        $table->setPrimaryKey(['id']);

        $this->connection->createSchemaManager()->createTable($table);
    }

    #[Teardown]
    public function drop(): void
    {
        $this->connection->createSchemaManager()->dropTable($this->tableName());
    }

    #[Subscribe(ProfileCreated::class)]
    public function handleProfileCreated(
        ProfileCreated $event,
        #[BatchState]
        BatchProfileState $state,
    ): void {
        $this->connection->insert(
            $this->tableName(),
            [
                'id' => $event->profileId->toString(),
                'name' => $event->name,
            ],
        );

        $state->insertedIds[] = $event->profileId->toString();
    }

    #[Subscribe(NameChanged::class)]
    public function handleNameChanged(
        NameChanged $event,
        #[BatchState]
        BatchProfileState $state,
    ): void {
        if ($event->name === self::POISON) {
            throw new RuntimeException('poisoned event');
        }

        $this->connection->update(
            $this->tableName(),
            ['name' => $event->name],
            ['id' => $event->profileId->toString()],
        );
    }

    private function tableName(): string
    {
        return 'projection_' . self::SUBSCRIBER_ID;
    }

    #[BatchBegin]
    public function beginBatch(): BatchProfileState
    {
        $this->beginCount++;
        $this->connection->beginTransaction();

        return new BatchProfileState();
    }

    #[BatchFlush]
    public function flush(BatchProfileState $state): void
    {
        $this->flushCount++;
        $this->connection->commit();
    }

    #[BatchRollback]
    public function rollbackBatch(BatchProfileState $state): void
    {
        $this->rollbackCount++;
        $this->connection->rollBack();
    }
}
