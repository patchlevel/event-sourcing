<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BasicImplementation;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\EventBus\SymfonyEventBus;
use Patchlevel\EventSourcing\Projection\DefaultProjectionRepository;
use Patchlevel\EventSourcing\Projection\ProjectionListener;
use Patchlevel\EventSourcing\Repository\DefaultRepository;
use Patchlevel\EventSourcing\Repository\SnapshotRepository;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaManager;
use Patchlevel\EventSourcing\Snapshot\InMemorySnapshotStore;
use Patchlevel\EventSourcing\Store\MultiTableStore;
use Patchlevel\EventSourcing\Store\SingleTableStore;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Aggregate\Profile;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Processor\SendEmailProcessor;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Projection\ProfileProjection;
use PHPUnit\Framework\TestCase;

use function getenv;

/**
 * @coversNothing
 */
final class BasicIntegrationTest extends TestCase
{
    private Connection $connection;

    public function setUp(): void
    {
        $this->connection = DriverManager::getConnection([
            'url' => getenv('DB_URL'),
        ]);
    }

    public function tearDown(): void
    {
        $this->connection->close();
        SendEmailMock::reset();
    }

    public function testSuccessful(): void
    {
        $profileProjection = new ProfileProjection($this->connection);
        $projectionRepository = new DefaultProjectionRepository(
            [$profileProjection]
        );

        $eventStream = new DefaultEventBus();
        $eventStream->addListener(new ProjectionListener($projectionRepository));
        $eventStream->addListener(new SendEmailProcessor());

        $store = new SingleTableStore(
            $this->connection,
            [Profile::class => 'profile'],
            'eventstore'
        );

        $repository = new DefaultRepository($store, $eventStream, Profile::class);

        // create tables
        $profileProjection->create();

        (new DoctrineSchemaManager())->create($store);

        $profile = Profile::create('1');
        $repository->save($profile);

        $result = $this->connection->fetchAssociative('SELECT * FROM projection_profile WHERE id = "1"');

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertEquals('1', $result['id']);

        $repository = new DefaultRepository($store, $eventStream, Profile::class);
        $profile = $repository->load('1');

        self::assertEquals('1', $profile->aggregateRootId());
        self::assertEquals(1, $profile->playhead());
        self::assertEquals(1, SendEmailMock::count());
    }

    public function testWithSymfonySuccessful(): void
    {
        $profileProjection = new ProfileProjection($this->connection);
        $projectionRepository = new DefaultProjectionRepository(
            [$profileProjection]
        );

        $eventStream = SymfonyEventBus::create([
            new ProjectionListener($projectionRepository),
            new SendEmailProcessor(),
        ]);

        $store = new SingleTableStore(
            $this->connection,
            [Profile::class => 'profile'],
            'eventstore'
        );

        $repository = new DefaultRepository($store, $eventStream, Profile::class);

        // create tables
        $profileProjection->create();
        (new DoctrineSchemaManager())->create($store);

        $profile = Profile::create('1');
        $repository->save($profile);

        $result = $this->connection->fetchAssociative('SELECT * FROM projection_profile WHERE id = "1"');

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertEquals('1', $result['id']);

        $repository = new DefaultRepository($store, $eventStream, Profile::class);
        $profile = $repository->load('1');

        self::assertEquals('1', $profile->aggregateRootId());
        self::assertEquals(1, $profile->playhead());
        self::assertEquals(1, SendEmailMock::count());
    }

    public function testMultiTableSuccessful(): void
    {
        $profileProjection = new ProfileProjection($this->connection);
        $projectionRepository = new DefaultProjectionRepository(
            [$profileProjection]
        );

        $eventStream = new DefaultEventBus();
        $eventStream->addListener(new ProjectionListener($projectionRepository));
        $eventStream->addListener(new SendEmailProcessor());

        $store = new MultiTableStore(
            $this->connection,
            [Profile::class => 'profile']
        );

        $repository = new DefaultRepository($store, $eventStream, Profile::class);

        // create tables
        $profileProjection->create();
        (new DoctrineSchemaManager())->create($store);

        $profile = Profile::create('1');
        $repository->save($profile);

        $result = $this->connection->fetchAssociative('SELECT * FROM projection_profile WHERE id = "1"');

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertEquals('1', $result['id']);

        $repository = new DefaultRepository($store, $eventStream, Profile::class);
        $profile = $repository->load('1');

        self::assertEquals('1', $profile->aggregateRootId());
        self::assertEquals(1, $profile->playhead());
        self::assertEquals(1, SendEmailMock::count());
    }

    public function testSnapshot(): void
    {
        $profileProjection = new ProfileProjection($this->connection);
        $projectionRepository = new DefaultProjectionRepository(
            [$profileProjection]
        );

        $eventStream = new DefaultEventBus();
        $eventStream->addListener(new ProjectionListener($projectionRepository));
        $eventStream->addListener(new SendEmailProcessor());

        $store = new SingleTableStore(
            $this->connection,
            [Profile::class => 'profile'],
            'eventstore'
        );

        $snapshotStore = new InMemorySnapshotStore();

        $repository = new SnapshotRepository($store, $eventStream, Profile::class, $snapshotStore);

        // create tables
        $profileProjection->create();
        (new DoctrineSchemaManager())->create($store);

        $profile = Profile::create('1');
        $repository->save($profile);

        $result = $this->connection->fetchAssociative('SELECT * FROM projection_profile WHERE id = "1"');

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertEquals('1', $result['id']);

        $repository = new SnapshotRepository($store, $eventStream, Profile::class, $snapshotStore);
        $profile = $repository->load('1');

        self::assertEquals('1', $profile->aggregateRootId());
        self::assertEquals(1, $profile->playhead());
        self::assertEquals(1, SendEmailMock::count());
    }
}
