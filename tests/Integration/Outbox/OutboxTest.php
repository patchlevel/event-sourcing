<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Outbox;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\EventBus\DefaultConsumer;
use Patchlevel\EventSourcing\Outbox\DoctrineOutboxStore;
use Patchlevel\EventSourcing\Outbox\EventBusPublisher;
use Patchlevel\EventSourcing\Outbox\OutboxEventBus;
use Patchlevel\EventSourcing\Outbox\StoreOutboxConsumer;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionCriteria;
use Patchlevel\EventSourcing\Projection\Projection\Store\InMemoryStore;
use Patchlevel\EventSourcing\Projection\Projectionist\DefaultProjectionist;
use Patchlevel\EventSourcing\Projection\Projectionist\SyncProjectionistEventBusWrapper;
use Patchlevel\EventSourcing\Projection\Projector\InMemoryProjectorRepository;
use Patchlevel\EventSourcing\Repository\DefaultRepository;
use Patchlevel\EventSourcing\Schema\ChainSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\DoctrineDbalStore;
use Patchlevel\EventSourcing\Tests\Integration\DbalManager;
use Patchlevel\EventSourcing\Tests\Integration\Outbox\Aggregate\Profile;
use Patchlevel\EventSourcing\Tests\Integration\Outbox\Events\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Integration\Outbox\Processor\SendEmailProcessor;
use Patchlevel\EventSourcing\Tests\Integration\Outbox\Projection\ProfileProjector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore as LockInMemoryStore;

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
        $serializer = DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']);

        $store = new DoctrineDbalStore(
            $this->connection,
            $serializer,
            'eventstore',
        );

        $outboxStore = new DoctrineOutboxStore(
            $this->connection,
            $serializer,
            'outbox',
        );

        $outboxEventBus = new OutboxEventBus($outboxStore);

        $profileProjector = new ProfileProjector($this->connection);
        $projectorRepository = new InMemoryProjectorRepository(
            [$profileProjector],
        );

        $projectionist = new DefaultProjectionist(
            $store,
            new InMemoryStore(),
            $projectorRepository,
        );

        $eventBusConsumer = DefaultConsumer::create([new SendEmailProcessor()]);

        $eventStream = new SyncProjectionistEventBusWrapper(
            $outboxEventBus,
            $projectionist,
            new LockFactory(
                new LockInMemoryStore(),
            ),
        );

        $repository = new DefaultRepository($store, $eventStream, Profile::metadata());

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            new ChainSchemaConfigurator([
                $store,
                $outboxStore,
            ]),
        );

        $schemaDirector->create();
        $projectionist->boot(new ProjectionCriteria(), null, true);

        $profile = Profile::create(ProfileId::fromString('1'), 'John');
        $repository->save($profile);

        self::assertSame(1, $outboxStore->countOutboxMessages());

        $messages = $outboxStore->retrieveOutboxMessages();

        self::assertCount(1, $messages);

        $message = $messages[0];

        self::assertSame('1', $message->aggregateId());
        self::assertSame('profile', $message->aggregateName());
        self::assertSame(1, $message->playhead());
        self::assertEquals(
            new ProfileCreated(ProfileId::fromString('1'), 'John'),
            $message->event(),
        );

        $consumer = new StoreOutboxConsumer(
            $outboxStore,
            new EventBusPublisher($eventBusConsumer),
        );

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
