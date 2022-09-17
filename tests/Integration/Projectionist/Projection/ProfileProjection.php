<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Projectionist\Projection;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
use Patchlevel\EventSourcing\Attribute\Create;
use Patchlevel\EventSourcing\Attribute\Drop;
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\Projector;
use Patchlevel\EventSourcing\Projection\ProjectorId;
use Patchlevel\EventSourcing\Tests\Integration\Projectionist\Events\ProfileCreated;

use function sprintf;

final class ProfileProjection implements Projector
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    #[Create]
    public function create(): void
    {
        $table = new Table($this->tableName());
        $table->addColumn('id', 'string');
        $table->addColumn('name', 'string');
        $table->setPrimaryKey(['id']);

        $this->connection->createSchemaManager()->createTable($table);
    }

    #[Drop]
    public function drop(): void
    {
        $this->connection->createSchemaManager()->dropTable($this->tableName());
    }

    #[Handle(ProfileCreated::class)]
    public function handleProfileCreated(Message $message): void
    {
        $profileCreated = $message->event();

        $this->connection->executeStatement(
            'INSERT INTO ' . $this->tableName() . ' (id, name) VALUES(:id, :name);',
            [
                'id' => $profileCreated->profileId->toString(),
                'name' => $profileCreated->name,
            ]
        );
    }

    private function tableName(): string
    {
        return sprintf(
            'projection_%s_%s',
            $this->projectorId()->name(),
            $this->projectorId()->version()
        );
    }

    public function projectorId(): ProjectorId
    {
        return new ProjectorId('profile', 1);
    }
}
