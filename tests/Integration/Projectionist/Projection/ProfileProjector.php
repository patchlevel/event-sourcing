<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Projectionist\Projection;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\Projector\ProjectorUtil;
use Patchlevel\EventSourcing\Tests\Integration\Projectionist\Events\ProfileCreated;

use function assert;

#[Projector('profile_1')]
final class ProfileProjector
{
    use ProjectorUtil;

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Setup]
    public function create(): void
    {
        $table = new Table($this->tableName());
        $table->addColumn('id', 'string')->setLength(36);
        $table->addColumn('name', 'string')->setLength(255);
        $table->setPrimaryKey(['id']);

        $this->connection->createSchemaManager()->createTable($table);
    }

    #[Teardown]
    public function drop(): void
    {
        $this->connection->createSchemaManager()->dropTable($this->tableName());
    }

    #[Subscribe(ProfileCreated::class)]
    public function handleProfileCreated(Message $message): void
    {
        $profileCreated = $message->event();

        assert($profileCreated instanceof ProfileCreated);

        $this->connection->executeStatement(
            'INSERT INTO ' . $this->tableName() . ' (id, name) VALUES(:id, :name);',
            [
                'id' => $profileCreated->profileId->toString(),
                'name' => $profileCreated->name,
            ],
        );
    }

    private function tableName(): string
    {
        return 'projection_' . $this->projectorId();
    }
}
