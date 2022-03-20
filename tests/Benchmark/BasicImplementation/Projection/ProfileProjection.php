<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Projection;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Attribute\Create;
use Patchlevel\EventSourcing\Attribute\Drop;
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\Projection\Projection;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Events\ProfileCreated;

final class ProfileProjection implements Projection
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    #[Create]
    public function create(): void
    {
        $this->connection->executeStatement('CREATE TABLE IF NOT EXISTS projection_profile (id VARCHAR PRIMARY KEY, name VARCHAR);');
    }

    #[Drop]
    public function drop(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS projection_profile;');
    }

    #[Handle(ProfileCreated::class)]
    public function handleProfileCreated(ProfileCreated $profileCreated): void
    {
        $this->connection->executeStatement(
            'INSERT INTO projection_profile (`id`, `name`) VALUES(:id, :name);',
            [
                'id' => $profileCreated->profileId->toString(),
                'name' => $profileCreated->name,
            ]
        );
    }
}
