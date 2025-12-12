<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Projection;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
use Patchlevel\EventSourcing\Attribute\Answer;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Events\NameChanged;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Events\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\ProfileId;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Query\QueryProfileName;

#[Projector('profile-1')]
final class ProfileProjector
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Setup]
    public function create(): void
    {
        $table = new Table('projection_profile');
        $table->addColumn('id', 'string')->setLength(36);
        $table->addColumn('name', 'string')->setLength(255);
        $table->setPrimaryKey(['id']);

        $this->connection->createSchemaManager()->createTable($table);
    }

    #[Teardown]
    public function drop(): void
    {
        $this->connection->createSchemaManager()->dropTable('projection_profile');
    }

    #[Subscribe(ProfileCreated::class)]
    public function handleProfileCreated(ProfileCreated $profileCreated): void
    {
        $this->connection->executeStatement(
            'INSERT INTO projection_profile (id, name) VALUES(:id, :name);',
            [
                'id' => $profileCreated->profileId->toString(),
                'name' => $profileCreated->name,
            ],
        );
    }

    #[Subscribe(NameChanged::class)]
    public function handleNameChanged(NameChanged $nameChanged, ProfileId $profileId): void
    {
        $this->connection->executeStatement(
            'UPDATE projection_profile SET name = :name WHERE id = :id;',
            [
                'id' => $profileId->toString(),
                'name' => $nameChanged->name,
            ],
        );
    }

    #[Answer]
    public function getProfileName(QueryProfileName $queryProfileName): string
    {
        return $this->connection->fetchAssociative(
            'SELECT name FROM projection_profile WHERE id = :id',
            ['id' => $queryProfileName->id->toString()],
        )['name'];
    }
}
