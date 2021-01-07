<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BasicImplementation;

use Doctrine\DBAL\Driver\PDO\SQLite\Driver;
use Doctrine\DBAL\DriverManager;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\Projection\ProjectionRepository;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Store\SQLiteSingleTableStore;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Aggregate\Profile;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Events\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Processor\SendEmailProcessor;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Projection\ProfileProjection;
use PHPUnit\Framework\TestCase;

final class BasicIntegrationTest extends TestCase
{
    public function testSuccessful(): void
    {
        $projectionConnection = DriverManager::getConnection([
            'driverClass' => Driver::class,
            'path'        => __DIR__ . '/data/db.sqlite3',
        ]);
        $profileProjection = new ProfileProjection($projectionConnection);
        $projectionRepository = new ProjectionRepository(
            [$profileProjection]
        );

        $eventStream = new DefaultEventBus();
        $eventStream->addListenerForAll($projectionRepository);
        $eventStream->addListener(ProfileCreated::class, new SendEmailProcessor());

        $eventConnection = DriverManager::getConnection([
            'driverClass' => Driver::class,
            'path'        => __DIR__ . '/data/db.sqlite3',
        ]);
        $store = new SQLiteSingleTableStore($eventConnection);
        $repository = new Repository($store, $eventStream, Profile::class);

        // create tables
        $profileProjection->create();
        $store->prepare();

        $profile = Profile::create('1');
        $repository->save($profile);

        $result = $projectionConnection->fetchAssociative('SELECT * FROM profile WHERE id = "1"');
        var_dump($result);

       # $profileProjection->drop();
       # $store->drop();
    }
}
