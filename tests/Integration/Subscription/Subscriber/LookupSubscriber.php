<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Reducer;
use Patchlevel\EventSourcing\Subscription\Lookup\Lookup;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberUtil;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\AdminPromoted;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\NameChanged;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\ProfileCreated;

#[Subscriber('lookup', RunMode::FromBeginning)]
final class LookupSubscriber
{
    use SubscriberUtil;

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Subscribe(AdminPromoted::class)]
    public function onAdminPromoted(AdminPromoted $event, Lookup $lookup): void
    {
        $messages = $lookup
            ->currentAggregate()
            ->events(
                ProfileCreated::class,
                NameChanged::class,
            )
            ->fetchAll();

        $state = (new Reducer())
            ->initState(['name' => null])
            ->when(ProfileCreated::class, static function (Message $message): array {
                return ['name' => $message->event()->name];
            })
            ->when(NameChanged::class, static function (Message $message): array {
                return ['name' => $message->event()->name];
            })
            ->reduce($messages);

        $this->connection->insert(
            $this->tableName(),
            [
                'id' => $event->profileId->toString(),
                'name' => $state['name'],
            ],
        );
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

    private function tableName(): string
    {
        return 'projection_' . $this->subscriberId();
    }
}
