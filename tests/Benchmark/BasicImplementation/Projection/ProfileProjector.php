<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Projection;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Attribute\Answer;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberUtil;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events\NameChanged;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Query\QueryProfileName;

#[Projector('profile')]
final class ProfileProjector
{
    use SubscriberUtil;

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Setup]
    public function create(): void
    {
        $this->connection->executeStatement("CREATE TABLE IF NOT EXISTS {$this->table()} (id VARCHAR PRIMARY KEY, name VARCHAR);");
    }

    #[Teardown]
    public function drop(): void
    {
        $this->connection->executeStatement("DROP TABLE IF EXISTS {$this->table()};");
    }

    #[Subscribe(ProfileCreated::class)]
    public function onProfileCreated(ProfileCreated $profileCreated): void
    {
        $this->connection->insert(
            $this->table(),
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
            $this->table(),
            ['name' => $nameChanged->name],
            ['id' => $nameChanged->profileId->toString()],
        );
    }

    #[Answer]
    public function getProfileName(QueryProfileName $queryProfileName): string
    {
        return $this->connection->fetchAssociative(
            "SELECT name FROM {$this->table()} WHERE id = :id;",
            ['id' => $queryProfileName->id->toString()],
        )['name'];
    }

    public function table(): string
    {
        return 'projection_' . $this->subscriberId();
    }
}
