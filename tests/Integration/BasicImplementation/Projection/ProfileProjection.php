<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Projection;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Projection\Projection;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Events\ProfileCreated;

final class ProfileProjection implements Projection
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /** @return iterable<class-string<AggregateChanged>, string> */
    public function getHandledMessages(): iterable
    {
        yield ProfileCreated::class => 'applyProfileCreated';
    }

    public function create(): void
    {
        $this->connection->executeStatement('CREATE TABLE IF NOT EXISTS profile (id VARCHAR PRIMARY KEY);');
    }

    public function drop(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS profile;');
    }

    public function applyProfileCreated(ProfileCreated $profileCreated): void
    {
        $this->connection->executeStatement(
            'INSERT INTO profile (`id`) VALUES(:id);',
            ['id' => $profileCreated->profileId()]
        );
    }
}
