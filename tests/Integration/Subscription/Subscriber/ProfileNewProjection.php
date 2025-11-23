<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\SubscriptionId;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\ProfileCreated;

#[Projector]
final class ProfileNewProjection
{
    #[SubscriptionId]
    private const TABLE_NAME = 'projection_profile_2';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Setup]
    public function create(): void
    {
        $table = new Table(self::TABLE_NAME);
        $table->addColumn('id', 'string')->setLength(36);
        $table->addColumn('firstname', 'string')->setLength(255);
        $table->setPrimaryKey(['id']);

        $this->connection->createSchemaManager()->createTable($table);
    }

    #[Teardown]
    public function drop(): void
    {
        $this->connection->createSchemaManager()->dropTable(self::TABLE_NAME);
    }

    #[Subscribe(ProfileCreated::class)]
    public function handleProfileCreated(ProfileCreated $profileCreated): void
    {
        $this->connection->executeStatement(
            'INSERT INTO ' . self::TABLE_NAME . ' (id, firstname) VALUES(:id, :firstname);',
            [
                'id' => $profileCreated->profileId->toString(),
                'firstname' => $profileCreated->name,
            ],
        );
    }
}
