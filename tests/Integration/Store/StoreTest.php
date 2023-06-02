<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Store;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AttributeAggregateRootRegistryFactory;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\SingleTableStore;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Tests\Integration\DbalManager;
use Patchlevel\EventSourcing\Tests\Integration\Store\Events\ProfileCreated;
use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
final class StoreTest extends TestCase
{
    private Connection $connection;
    private Store $store;

    public function setUp(): void
    {
        $this->connection = DbalManager::createConnection();

        $this->store = new SingleTableStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            new AggregateRootRegistry([]),
            'eventstore'
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $this->store
        );

        $schemaDirector->create();
    }

    public function tearDown(): void
    {
        $this->connection->close();
    }

    public function testSave(): void
    {
        $messages = [
            Message::create(new ProfileCreated(ProfileId::fromString('test'), 'test')),
            Message::create(new ProfileCreated(ProfileId::fromString('test'), 'test')),
        ];

        $this->store->save(...$messages);
    }
}
