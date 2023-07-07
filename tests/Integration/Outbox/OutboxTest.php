<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Outbox;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AttributeAggregateRootRegistryFactory;
use Patchlevel\EventSourcing\Outbox\DoctrineOutboxStore;
use Patchlevel\EventSourcing\Outbox\OutboxEventBus;
use Patchlevel\EventSourcing\Outbox\StoreOutboxConsumer;
use Patchlevel\EventSourcing\Projection\Projector\InMemoryProjectorRepository;
use Patchlevel\EventSourcing\Projection\Projector\SyncProjectorListener;
use Patchlevel\EventSourcing\Repository\DefaultRepository;
use Patchlevel\EventSourcing\Schema\ChainSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\DoctrineDbalStore;
use Patchlevel\EventSourcing\Tests\Integration\DbalManager;
use Patchlevel\EventSourcing\Tests\Integration\Outbox\Aggregate\Profile;
use Patchlevel\EventSourcing\Tests\Integration\Outbox\Events\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Integration\Outbox\Processor\SendEmailProcessor;
use Patchlevel\EventSourcing\Tests\Integration\Outbox\Projection\ProfileProjection;
use PHPUnit\Framework\TestCase;

/** @coversNothing */
final class OutboxTest extends TestCase
{
    private Connection $connection;

    public function setUp(): void
    {
        $this->connection = DbalManager::createConnection();
    }

    public function tearDown(): void
    {
        $this->connection->close();
        SendEmailMock::reset();
    }

    public function testSuccessful(): void
    {
        $profileProjection = new ProfileProjection($this->connection);
        $projectionRepository = new InMemoryProjectorRepository(
            [$profileProjection],
        );

        $serializer = DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']);
        $registry = (new AttributeAggregateRootRegistryFactory())->create([__DIR__ . '/Aggregate']);

        $store = new DoctrineDbalStore(
            $this->connection,
            $serializer,
            $registry,
            'eventstore',
        );

        $outboxStore = new DoctrineOutboxStore(
            $this->connection,
            $serializer,
            $registry,
            'outbox',
        );

        $realEventBus = new DefaultEventBus();
        $realEventBus->addListener(new SyncProjectorListener($projectionRepository));
        $realEventBus->addListener(new SendEmailProcessor());

        $outboxEventBus = new OutboxEventBus($outboxStore);
        $repository = new DefaultRepository($store, $outboxEventBus, Profile::metadata());

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            new ChainSchemaConfigurator([
                $store,
                $outboxStore,
            ]),
        );

        $schemaDirector->create();
        $profileProjection->create();

        $profile = Profile::create(ProfileId::fromString('1'), 'John');
        $repository->save($profile);

        self::assertSame(1, $outboxStore->countOutboxMessages());

        $messages = $outboxStore->retrieveOutboxMessages();

        self::assertCount(1, $messages);

        $message = $messages[0];

        self::assertSame('1', $message->aggregateId());
        self::assertSame(Profile::class, $message->aggregateClass());
        self::assertSame(1, $message->playhead());
        self::assertEquals(
            new ProfileCreated(ProfileId::fromString('1'), 'John'),
            $message->event(),
        );

        $consumer = new StoreOutboxConsumer($outboxStore, $realEventBus);
        $consumer->consume();

        self::assertSame(0, $outboxStore->countOutboxMessages());
        self::assertCount(0, $outboxStore->retrieveOutboxMessages());

        $result = $this->connection->fetchAssociative('SELECT * FROM projection_profile WHERE id = ?', ['1']);

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertSame('1', $result['id']);
        self::assertSame('John', $result['name']);
    }
}
