<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BasicImplementation;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Clock\SystemClock;
use Patchlevel\EventSourcing\EventBus\Decorator\ChainMessageDecorator;
use Patchlevel\EventSourcing\EventBus\Decorator\RecordedOnDecorator;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\EventBus\SymfonyEventBus;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AttributeAggregateRootRegistryFactory;
use Patchlevel\EventSourcing\Projection\MetadataAwareProjectionHandler;
use Patchlevel\EventSourcing\Projection\ProjectionListener;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaManager;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Snapshot\Adapter\InMemorySnapshotAdapter;
use Patchlevel\EventSourcing\Snapshot\DefaultSnapshotStore;
use Patchlevel\EventSourcing\Store\MultiTableStore;
use Patchlevel\EventSourcing\Store\SingleTableStore;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Aggregate\Profile;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\MessageDecorator\FooMessageDecorator;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Processor\SendEmailProcessor;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Projection\ProfileProjection;
use Patchlevel\EventSourcing\Tests\Integration\DbalManager;
use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
final class BasicIntegrationTest extends TestCase
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
        $projectionRepository = new MetadataAwareProjectionHandler(
            [$profileProjection]
        );

        $eventStream = new DefaultEventBus();
        $eventStream->addListener(new ProjectionListener($projectionRepository));
        $eventStream->addListener(new SendEmailProcessor());

        $store = new SingleTableStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            (new AttributeAggregateRootRegistryFactory())->create([__DIR__ . '/Aggregate']),
            'eventstore'
        );

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => Profile::class]),
            $store,
            $eventStream,
            null,
            new ChainMessageDecorator([new RecordedOnDecorator(new SystemClock()), new FooMessageDecorator()])
        );
        $repository = $manager->get(Profile::class);

        // create tables
        $profileProjection->create();
        (new DoctrineSchemaManager())->create($store);

        $profile = Profile::create(ProfileId::fromString('1'), 'John');
        $repository->save($profile);

        $result = $this->connection->fetchAssociative('SELECT * FROM projection_profile WHERE id = ?', ['1']);

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertSame('1', $result['id']);
        self::assertSame('John', $result['name']);

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => Profile::class]),
            $store,
            $eventStream,
            null,
            new ChainMessageDecorator([new RecordedOnDecorator(new SystemClock())])
        );
        $repository = $manager->get(Profile::class);
        $profile = $repository->load('1');

        self::assertInstanceOf(Profile::class, $profile);
        self::assertSame('1', $profile->aggregateRootId());
        self::assertSame(1, $profile->playhead());
        self::assertSame('John', $profile->name());
        self::assertSame(1, SendEmailMock::count());
    }

    public function testWithSymfonySuccessful(): void
    {
        $profileProjection = new ProfileProjection($this->connection);
        $projectionRepository = new MetadataAwareProjectionHandler(
            [$profileProjection]
        );

        $eventStream = SymfonyEventBus::create([
            new ProjectionListener($projectionRepository),
            new SendEmailProcessor(),
        ]);

        $store = new SingleTableStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            (new AttributeAggregateRootRegistryFactory())->create([__DIR__ . '/Aggregate']),
            'eventstore'
        );

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => Profile::class]),
            $store,
            $eventStream,
            null,
            new ChainMessageDecorator([new RecordedOnDecorator(new SystemClock())])
        );
        $repository = $manager->get(Profile::class);

        // create tables
        $profileProjection->create();
        (new DoctrineSchemaManager())->create($store);

        $profile = Profile::create(ProfileId::fromString('1'), 'John');
        $repository->save($profile);

        $result = $this->connection->fetchAssociative('SELECT * FROM projection_profile WHERE id = ?', ['1']);

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertSame('1', $result['id']);
        self::assertSame('John', $result['name']);

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => Profile::class]),
            $store,
            $eventStream,
            null,
            new ChainMessageDecorator([new RecordedOnDecorator(new SystemClock()), new FooMessageDecorator()])
        );
        $repository = $manager->get(Profile::class);

        $profile = $repository->load('1');

        self::assertInstanceOf(Profile::class, $profile);
        self::assertSame('1', $profile->aggregateRootId());
        self::assertSame(1, $profile->playhead());
        self::assertSame('John', $profile->name());
        self::assertSame(1, SendEmailMock::count());
    }

    public function testMultiTableSuccessful(): void
    {
        $profileProjection = new ProfileProjection($this->connection);
        $projectionRepository = new MetadataAwareProjectionHandler(
            [$profileProjection]
        );

        $eventStream = new DefaultEventBus();
        $eventStream->addListener(new ProjectionListener($projectionRepository));
        $eventStream->addListener(new SendEmailProcessor());

        $store = new MultiTableStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            (new AttributeAggregateRootRegistryFactory())->create([__DIR__ . '/Aggregate']),
        );

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => Profile::class]),
            $store,
            $eventStream,
            null,
            new ChainMessageDecorator([new RecordedOnDecorator(new SystemClock()), new FooMessageDecorator()])
        );
        $repository = $manager->get(Profile::class);

        // create tables
        $profileProjection->create();
        (new DoctrineSchemaManager())->create($store);

        $profile = Profile::create(ProfileId::fromString('1'), 'John');
        $repository->save($profile);

        $result = $this->connection->fetchAssociative('SELECT * FROM projection_profile WHERE id = ?', ['1']);

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertSame('1', $result['id']);
        self::assertSame('John', $result['name']);

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => Profile::class]),
            $store,
            $eventStream,
            null,
            new ChainMessageDecorator([new RecordedOnDecorator(new SystemClock())])
        );
        $repository = $manager->get(Profile::class);
        $profile = $repository->load('1');

        self::assertInstanceOf(Profile::class, $profile);
        self::assertSame('1', $profile->aggregateRootId());
        self::assertSame(1, $profile->playhead());
        self::assertSame('John', $profile->name());
        self::assertSame(1, SendEmailMock::count());
    }

    public function testSnapshot(): void
    {
        $profileProjection = new ProfileProjection($this->connection);
        $projectionRepository = new MetadataAwareProjectionHandler(
            [$profileProjection]
        );

        $eventStream = new DefaultEventBus();
        $eventStream->addListener(new ProjectionListener($projectionRepository));
        $eventStream->addListener(new SendEmailProcessor());

        $store = new SingleTableStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            (new AttributeAggregateRootRegistryFactory())->create([__DIR__ . '/Aggregate']),
            'eventstore'
        );

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => Profile::class]),
            $store,
            $eventStream,
            new DefaultSnapshotStore(['default' => new InMemorySnapshotAdapter()]),
            new ChainMessageDecorator([new RecordedOnDecorator(new SystemClock()), new FooMessageDecorator()])
        );
        $repository = $manager->get(Profile::class);

        // create tables
        $profileProjection->create();
        (new DoctrineSchemaManager())->create($store);

        $profile = Profile::create(ProfileId::fromString('1'), 'John');
        $repository->save($profile);

        $result = $this->connection->fetchAssociative('SELECT * FROM projection_profile WHERE id = ?', ['1']);

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertSame('1', $result['id']);
        self::assertSame('John', $result['name']);

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => Profile::class]),
            $store,
            $eventStream,
            null,
            new ChainMessageDecorator([new RecordedOnDecorator(new SystemClock())])
        );
        $repository = $manager->get(Profile::class);
        $profile = $repository->load('1');

        self::assertInstanceOf(Profile::class, $profile);
        self::assertSame('1', $profile->aggregateRootId());
        self::assertSame(1, $profile->playhead());
        self::assertSame('John', $profile->name());
        self::assertSame(1, SendEmailMock::count());
    }
}
