<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Projection;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
use Patchlevel\EventSourcing\Projection\Projection;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Events\ProfileCreated;

final class ProfileProjection implements Projection
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function handledEvents(): iterable
    {
        yield ProfileCreated::class => 'applyProfileCreated';
    }

    public function create(): void
    {
        $table = new Table('projection_profile');
        $table->addColumn('id', 'string');
        $table->addColumn('name', 'string');
        $table->setPrimaryKey(['id']);
    }

    public function drop(): void
    {
        $this->connection->createSchemaManager()->dropTable('projection_profile');
    }

    public function applyProfileCreated(ProfileCreated $profileCreated): void
    {
        $this->connection->executeStatement(
            'INSERT INTO projection_profile (id, name) VALUES(:id, :name);',
            [
                'id' => $profileCreated->profileId(),
                'name' => $profileCreated->name(),
            ]
        );
    }
}
