<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Outbox;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\EventBus\DefaultConsumer;
use Patchlevel\EventSourcing\EventBus\Serializer\PhpNativeMessageSerializer;
use Patchlevel\EventSourcing\Outbox\DoctrineOutboxStore;
use Patchlevel\EventSourcing\Outbox\EventBusPublisher;
use Patchlevel\EventSourcing\Outbox\OutboxEventBus;
use Patchlevel\EventSourcing\Outbox\StoreOutboxProcessor;
use Patchlevel\EventSourcing\Repository\DefaultRepository;
use Patchlevel\EventSourcing\Schema\ChainDoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\DoctrineDbalStore;
use Patchlevel\EventSourcing\Tests\DbalManager;
use Patchlevel\EventSourcing\Tests\Integration\Outbox\Aggregate\Profile;
use Patchlevel\EventSourcing\Tests\Integration\Outbox\Events\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Integration\Outbox\Processor\SendEmailProcessor;
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
        $serializer = DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']);

        $store = new DoctrineDbalStore(
            $this->connection,
            $serializer,
            'eventstore',
        );

        $outboxStore = new DoctrineOutboxStore(
            $this->connection,
            new PhpNativeMessageSerializer(),
            'outbox',
        );

        $outboxEventBus = new OutboxEventBus($outboxStore);

        $eventBusConsumer = DefaultConsumer::create([new SendEmailProcessor()]);

        $eventBus = $outboxEventBus;

        $repository = new DefaultRepository($store, $eventBus, Profile::metadata());

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            new ChainDoctrineSchemaConfigurator([
                $store,
                $outboxStore,
            ]),
        );

        $schemaDirector->create();

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

        $consumer = new StoreOutboxProcessor(
            $outboxStore,
            new EventBusPublisher($eventBusConsumer),
        );

        $consumer->process();

        self::assertSame(0, $outboxStore->countOutboxMessages());
        self::assertCount(0, $outboxStore->retrieveOutboxMessages());
    }
}
