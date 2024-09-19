<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
use Patchlevel\EventSourcing\Attribute\Batch;
use Patchlevel\EventSourcing\Attribute\BeginBatch;
use Patchlevel\EventSourcing\Attribute\CommitBatch;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\RollbackBatch;
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberUtil;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\ProfileCreated;

#[Projector('profile_1')]
#[Batch]
final class ProfileProjection
{
    use SubscriberUtil;

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
    public function handleProfileCreated(ProfileCreated $profileCreated): void
    {
        $this->connection->executeStatement(
            'INSERT INTO ' . $this->tableName() . ' (id, name) VALUES(:id, :name);',
            [
                'id' => $profileCreated->profileId->toString(),
                'name' => $profileCreated->name,
            ],
        );
    }

    private function tableName(): string
    {
        return 'projection_' . $this->subscriberId();
    }


    #[BeginBatch]
    public function beginBatch(): void
    {
        $this->connection->beginTransaction();
    }

    #[CommitBatch]
    public function commitBatch(): void
    {
        $this->connection->commit();
    }

    #[RollbackBatch]
    public function rollbackBatch(): void
    {
        $this->connection->rollBack();
    }
}
