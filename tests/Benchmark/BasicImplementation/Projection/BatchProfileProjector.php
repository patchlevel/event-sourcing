<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Projection;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\Subscription\Subscriber\BatchableSubscriber;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events\NameChanged;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events\ProfileCreated;

#[Projector(self::SUBSCRIBER_ID)]
final class BatchProfileProjector implements BatchableSubscriber
{
    private const SUBSCRIBER_ID = 'profile';

    /** @var array<string, string> */
    private array $nameChanged = [];

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
        $this->nameChanged[$nameChanged->profileId->toString()] = $nameChanged->name;
    }

    public function table(): string
    {
        return 'projection_' . self::SUBSCRIBER_ID;
    }

    public function beginBatch(): void
    {
        $this->nameChanged = [];
    }

    public function commitBatch(): void
    {
        try {
            $this->connection->transactional(function (): void {
                foreach ($this->nameChanged as $profileId => $name) {
                    $this->connection->update(
                        $this->table(),
                        ['name' => $name],
                        ['id' => $profileId],
                    );
                }
            });
        } finally {
            $this->nameChanged = [];
        }
    }

    public function rollbackBatch(): void
    {
        $this->nameChanged = [];
    }

    public function forceCommit(): bool
    {
        return false;
    }
}
