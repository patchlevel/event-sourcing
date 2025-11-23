<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Projection;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\SubscriptionId;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\Subscription\Subscriber\BatchableSubscriber;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events\NameChanged;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events\ProfileCreated;

use function sprintf;

#[Projector]
final class BatchProfileProjector implements BatchableSubscriber
{
    #[SubscriptionId]
    private const TABLE_NAME = 'projection_profile';

    /** @var array<string, string> */
    private array $nameChanged = [];

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
        $this->nameChanged[$nameChanged->profileId->toString()] = $nameChanged->name;
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
                        self::TABLE_NAME,
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
