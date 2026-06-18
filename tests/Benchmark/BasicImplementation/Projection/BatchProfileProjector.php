<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Projection;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Attribute\BatchBegin;
use Patchlevel\EventSourcing\Attribute\BatchFlush;
use Patchlevel\EventSourcing\Attribute\BatchRollback;
use Patchlevel\EventSourcing\Attribute\BatchState;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events\NameChanged;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events\ProfileCreated;

#[Projector(self::SUBSCRIBER_ID)]
final class BatchProfileProjector
{
    private const SUBSCRIBER_ID = 'profile';

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
    public function onNameChanged(
        NameChanged $nameChanged,
        #[BatchState]
        BatchProfileState $state,
    ): void {
        $state->nameChanged[$nameChanged->profileId->toString()] = $nameChanged->name;
    }

    public function table(): string
    {
        return 'projection_' . self::SUBSCRIBER_ID;
    }

    #[BatchBegin]
    public function beginBatch(): BatchProfileState
    {
        return new BatchProfileState();
    }

    #[BatchFlush]
    public function flush(BatchProfileState $state): void
    {
        $this->connection->transactional(function () use ($state): void {
            foreach ($state->nameChanged as $profileId => $name) {
                $this->connection->update(
                    $this->table(),
                    ['name' => $name],
                    ['id' => $profileId],
                );
            }
        });
    }

    #[BatchRollback]
    public function rollbackBatch(BatchProfileState $state): void
    {
        $state->nameChanged = [];
    }
}
