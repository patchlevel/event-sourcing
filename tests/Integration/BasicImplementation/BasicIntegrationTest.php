<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BasicImplementation;

use Doctrine\DBAL\Driver\PDO\SQLite\Driver;
use Doctrine\DBAL\DriverManager;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\EventBus\SymfonyEventBus;
use Patchlevel\EventSourcing\Projection\ProjectionListener;
use Patchlevel\EventSourcing\Projection\ProjectionRepository;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Store\MultiTableStore;
use Patchlevel\EventSourcing\Store\SingleTableStore;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Aggregate\Profile;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Processor\SendEmailProcessor;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Projection\ProfileProjection;
use PHPUnit\Framework\TestCase;

final class BasicIntegrationTest extends TestCase
{
    public function testSuccessful(): void
    {
        $connection = DriverManager::getConnection([
            'driverClass' => Driver::class,
            'path' => __DIR__ . '/data/db.sqlite3',
        ]);

        $profileProjection = new ProfileProjection($connection);
        $projectionRepository = new ProjectionRepository(
            [$profileProjection]
        );

        $eventStream = new DefaultEventBus();
        $eventStream->addListener(new ProjectionListener($projectionRepository));
        $eventStream->addListener(new SendEmailProcessor());

        $store = new SingleTableStore($connection);
        $repository = new Repository($store, $eventStream, Profile::class);

        // create tables
        $profileProjection->create();
        $store->prepare();

        $profile = Profile::create('1');
        $repository->save($profile);

        $result = $connection->fetchAssociative('SELECT * FROM profile WHERE id = "1"');
        self::assertArrayHasKey('id', $result);
        self::assertEquals('1', $result['id']);

        $profileProjection->drop();
        $store->drop();
    }

    public function testWithSymfonySuccessful(): void
    {
        $connection = DriverManager::getConnection([
            'driverClass' => Driver::class,
            'path' => __DIR__ . '/data/db.sqlite3',
        ]);

        $profileProjection = new ProfileProjection($connection);
        $projectionRepository = new ProjectionRepository(
            [$profileProjection]
        );

        $eventStream = SymfonyEventBus::create([
            new ProjectionListener($projectionRepository),
            new SendEmailProcessor(),
        ]);

        $store = new SingleTableStore($connection);
        $repository = new Repository($store, $eventStream, Profile::class);

        // create tables
        $profileProjection->create();
        $store->prepare();

        $profile = Profile::create('1');
        $repository->save($profile);

        $result = $connection->fetchAssociative('SELECT * FROM profile WHERE id = "1"');
        self::assertArrayHasKey('id', $result);
        self::assertEquals('1', $result['id']);

        $profileProjection->drop();
        $store->drop();
    }

    public function testMultiTableSuccessful(): void
    {
        $connection = DriverManager::getConnection([
            'driverClass' => Driver::class,
            'path' => __DIR__ . '/data/db.sqlite3',
        ]);

        $profileProjection = new ProfileProjection($connection);
        $projectionRepository = new ProjectionRepository(
            [$profileProjection]
        );

        $eventStream = new DefaultEventBus();
        $eventStream->addListener(new ProjectionListener($projectionRepository));
        $eventStream->addListener(new SendEmailProcessor());

        $store = new MultiTableStore($connection, [
            Profile::class
        ]);

        $repository = new Repository($store, $eventStream, Profile::class);

        // create tables
        $profileProjection->create();
        $store->prepare();

        $profile = Profile::create('1');
        $repository->save($profile);

        $result = $connection->fetchAssociative('SELECT * FROM profile WHERE id = "1"');
        self::assertArrayHasKey('id', $result);
        self::assertEquals('1', $result['id']);

        $profileProjection->drop();
        $store->drop();
    }
}
