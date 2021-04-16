<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Projection;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Projection\Projection;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events\NameChanged;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events\ProfileCreated;

final class ProfileProjection implements Projection
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /** @return iterable<class-string<AggregateChanged>, string> */
    public function handledEvents(): iterable
    {
        yield ProfileCreated::class => 'applyProfileCreated';
        yield NameChanged::class => 'applyNameChanged';
    }

    public function create(): void
    {
        $this->connection->executeStatement('CREATE TABLE IF NOT EXISTS projection_profile (id VARCHAR PRIMARY KEY, name VARCHAR);');
    }

    public function drop(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS projection_profile;');
    }

    public function applyProfileCreated(ProfileCreated $profileCreated): void
    {
        $this->connection->executeStatement(
            'INSERT INTO projection_profile (`id`, `name`) VALUES(:id, :name);',
            [
                'id' => $profileCreated->profileId(),
                'name' => $profileCreated->name(),
            ]
        );
    }

    public function applyNameChanged(NameChanged $nameChanged): void
    {
        $this->connection->executeStatement(
            'UPDATE projection_profile SET name = :name WHERE id = :id;',
            [
                'id' => $nameChanged->aggregateId(),
                'name' => $nameChanged->name(),
            ]
        );
    }
}
