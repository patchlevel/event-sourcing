<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Projection;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Attribute\Answer;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\SubscriptionId;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events\NameChanged;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Query\QueryProfileName;

use function sprintf;

#[Projector('profile')]
final class ProfileProjector
{
    #[SubscriptionId]
    private const TABLE_NAME = 'projection_profile';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Setup]
    public function create(): void
    {
        $this->connection->executeStatement(sprintf(
            'CREATE TABLE IF NOT EXISTS %s (id VARCHAR PRIMARY KEY, name VARCHAR);',
            self::TABLE_NAME,
        ));
    }

    #[Teardown]
    public function drop(): void
    {
        $this->connection->executeStatement(sprintf('DROP TABLE IF EXISTS %s;', self::TABLE_NAME));
    }

    #[Subscribe(ProfileCreated::class)]
    public function onProfileCreated(ProfileCreated $profileCreated): void
    {
        $this->connection->insert(
            self::TABLE_NAME,
            [
                'id' => $profileCreated->profileId->toString(),
                'name' => $profileCreated->name,
            ],
        );
    }

    #[Subscribe(NameChanged::class)]
    public function onNameChanged(NameChanged $nameChanged): void
    {
        $this->connection->update(
            self::TABLE_NAME,
            ['name' => $nameChanged->name],
            ['id' => $nameChanged->profileId->toString()],
        );
    }

    #[Answer]
    public function getProfileName(QueryProfileName $queryProfileName): string
    {
        return $this->connection->fetchAssociative(
            sprintf('SELECT name FROM %s WHERE id = :id;', self::TABLE_NAME),
            ['id' => $queryProfileName->id->toString()],
        )['name'];
    }
}
