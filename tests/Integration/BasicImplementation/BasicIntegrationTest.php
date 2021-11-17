<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BasicImplementation;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\SQLite\Driver;
use Doctrine\DBAL\DriverManager;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\EventBus\SymfonyEventBus;
use Patchlevel\EventSourcing\Projection\DefaultProjectionRepository;
use Patchlevel\EventSourcing\Projection\ProjectionListener;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaManager;
use Patchlevel\EventSourcing\Snapshot\InMemorySnapshotStore;
use Patchlevel\EventSourcing\Store\MultiTableStore;
use Patchlevel\EventSourcing\Store\SingleTableStore;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Aggregate\Profile;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Processor\SendEmailProcessor;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Projection\ProfileProjection;
use PHPUnit\Framework\TestCase;

use function file_exists;
use function unlink;

/**
 * @coversNothing
 */
final class BasicIntegrationTest extends TestCase
{
    private Connection $connection;

    private const DB_PATH = __DIR__ . '/data/db.sqlite3';

    public function setUp(): void
    {
        if (file_exists(self::DB_PATH)) {
            unlink(self::DB_PATH);
        }

        $this->connection = DriverManager::getConnection([
            'driverClass' => Driver::class,
            'path' => self::DB_PATH,
        ]);
    }

    public function tearDown(): void
    {
        $this->connection->close();
        SendEmailMock::reset();

        unlink(self::DB_PATH);
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

        $repository = new Repository($store, $eventStream, Profile::class);

        // create tables
        $profileProjection->create();

        (new DoctrineSchemaManager())->create($store);

        $profile = Profile::create('1');
        $repository->save($profile);

        $result = $this->connection->fetchAssociative('SELECT * FROM projection_profile WHERE id = "1"');
        self::assertArrayHasKey('id', $result);
        self::assertEquals('1', $result['id']);

        $repository = new Repository($store, $eventStream, Profile::class);
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

        $repository = new Repository($store, $eventStream, Profile::class);

        // create tables
        $profileProjection->create();
        (new DoctrineSchemaManager())->create($store);

        $profile = Profile::create('1');
        $repository->save($profile);

        $result = $this->connection->fetchAssociative('SELECT * FROM projection_profile WHERE id = "1"');
        self::assertArrayHasKey('id', $result);
        self::assertEquals('1', $result['id']);

        $repository = new Repository($store, $eventStream, Profile::class);
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

        $repository = new Repository($store, $eventStream, Profile::class);

        // create tables
        $profileProjection->create();
        (new DoctrineSchemaManager())->create($store);

        $profile = Profile::create('1');
        $repository->save($profile);

        $result = $this->connection->fetchAssociative('SELECT * FROM projection_profile WHERE id = "1"');
        self::assertArrayHasKey('id', $result);
        self::assertEquals('1', $result['id']);

        $repository = new Repository($store, $eventStream, Profile::class);
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

        $repository = new Repository($store, $eventStream, Profile::class, $snapshotStore);

        // create tables
        $profileProjection->create();
        (new DoctrineSchemaManager())->create($store);

        $profile = Profile::create('1');
        $repository->save($profile);

        $result = $this->connection->fetchAssociative('SELECT * FROM projection_profile WHERE id = "1"');
        self::assertArrayHasKey('id', $result);
        self::assertEquals('1', $result['id']);

        $repository = new Repository($store, $eventStream, Profile::class, $snapshotStore);
        $profile = $repository->load('1');

        self::assertEquals('1', $profile->aggregateRootId());
        self::assertEquals(1, $profile->playhead());
        self::assertEquals(1, SendEmailMock::count());
    }
}
