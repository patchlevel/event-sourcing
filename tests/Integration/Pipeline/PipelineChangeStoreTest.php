<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Pipeline;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\SQLite\Driver;
use Doctrine\DBAL\DriverManager;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\Pipeline\Middleware\DeleteEventMiddleware;
use Patchlevel\EventSourcing\Pipeline\Middleware\RecalculatePlayheadMiddleware;
use Patchlevel\EventSourcing\Pipeline\Middleware\ReplaceEventMiddleware;
use Patchlevel\EventSourcing\Pipeline\Pipeline;
use Patchlevel\EventSourcing\Pipeline\Source\StoreSource;
use Patchlevel\EventSourcing\Pipeline\Target\StoreTarget;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaManager;
use Patchlevel\EventSourcing\Store\SingleTableStore;
use Patchlevel\EventSourcing\Tests\Integration\Pipeline\Aggregate\Profile;
use Patchlevel\EventSourcing\Tests\Integration\Pipeline\Events\NewVisited;
use Patchlevel\EventSourcing\Tests\Integration\Pipeline\Events\OldVisited;
use Patchlevel\EventSourcing\Tests\Integration\Pipeline\Events\PrivacyAdded;
use PHPUnit\Framework\TestCase;

use function file_exists;
use function unlink;

final class PipelineChangeStoreTest extends TestCase
{
    private Connection $connectionOld;
    private Connection $connectionNew;

    private const DB_PATH_OLD = __DIR__ . '/data/old.sqlite3';
    private const DB_PATH_NEW = __DIR__ . '/data/new.sqlite3';

    public function setUp(): void
    {
        if (file_exists(self::DB_PATH_OLD)) {
            unlink(self::DB_PATH_OLD);
        }

        if (file_exists(self::DB_PATH_NEW)) {
            unlink(self::DB_PATH_NEW);
        }

        $this->connectionOld = DriverManager::getConnection([
            'driverClass' => Driver::class,
            'path' => self::DB_PATH_OLD,
        ]);

        $this->connectionNew = DriverManager::getConnection([
            'driverClass' => Driver::class,
            'path' => self::DB_PATH_NEW,
        ]);
    }

    public function tearDown(): void
    {
        $this->connectionOld->close();
        $this->connectionNew->close();

        unlink(self::DB_PATH_OLD);
        unlink(self::DB_PATH_NEW);
    }

    public function testSuccessful(): void
    {
        $oldStore = new SingleTableStore(
            $this->connectionOld,
            [Profile::class => 'profile'],
            'eventstore'
        );

        (new DoctrineSchemaManager())->create($oldStore);

        $newStore = new SingleTableStore(
            $this->connectionNew,
            [Profile::class => 'profile'],
            'eventstore'
        );

        (new DoctrineSchemaManager())->create($newStore);

        $oldRepository = new Repository($oldStore, new DefaultEventBus(), Profile::class);
        $newRepository = new Repository($newStore, new DefaultEventBus(), Profile::class);

        $profile = Profile::create('1');
        $profile->visit();
        $profile->privacy();
        $profile->visit();

        $oldRepository->save($profile);

        self::assertEquals('1', $profile->aggregateRootId());
        self::assertEquals(3, $profile->playhead());
        self::assertEquals(true, $profile->isPrivate());
        self::assertEquals(2, $profile->count());

        $pipeline = new Pipeline(
            new StoreSource($oldStore),
            new StoreTarget($newStore),
            [
                new DeleteEventMiddleware([PrivacyAdded::class]),
                new ReplaceEventMiddleware(OldVisited::class, static function (OldVisited $oldVisited) {
                    return NewVisited::raise($oldVisited->profileId());
                }),
                new RecalculatePlayheadMiddleware(),
            ]
        );

        self::assertEquals(4, $pipeline->count());
        $pipeline->run();

        $newProfile = $newRepository->load('1');

        self::assertEquals('1', $newProfile->aggregateRootId());
        self::assertEquals(2, $newProfile->playhead());
        self::assertEquals(false, $newProfile->isPrivate());
        self::assertEquals(-2, $newProfile->count());
    }
}
