<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Projectionist\Projection;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
use Patchlevel\EventSourcing\Attribute\Create;
use Patchlevel\EventSourcing\Attribute\Drop;
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionId;
use Patchlevel\EventSourcing\Projection\Projector\Projector;
use Patchlevel\EventSourcing\Tests\Integration\Projectionist\Events\ProfileCreated;

use function assert;
use function sprintf;

final class ProfileProjection implements Projector
{
    public function __construct(
        private Connection $connection,
    ) {
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
        return sprintf(
            'projection_%s_%s',
            $this->targetProjection()->name(),
            $this->targetProjection()->version(),
        );
    }

    public function targetProjection(): ProjectionId
    {
        return new ProjectionId('profile', 1);
    }
}
