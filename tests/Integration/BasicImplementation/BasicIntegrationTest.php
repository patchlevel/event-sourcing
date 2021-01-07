<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BasicImplementation;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\SQLite\Driver;
use Patchlevel\EventSourcing\EventBus\EventStream;
use Patchlevel\EventSourcing\Projection\ProjectionRepository;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Store\MysqlSingleTableStore;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Aggregate\Profile;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Events\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Processor\SendEmailProcessor;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Projection\ProfileProjection;
use PHPUnit\Framework\TestCase;

final class BasicIntegrationTest extends TestCase
{
    public function testSuccessful(): void
    {
        $projectionConnection = new Connection(['url' => 'sqlite:///somedb.sqlite'], new Driver());
        $projectionRepository = new ProjectionRepository(
            [new ProfileProjection($projectionConnection)]
        );

        $eventStream = new EventStream();
        $eventStream->addListener(ProfileCreated::class, new SendEmailProcessor());
        $eventStream->addListener(ProfileCreated::class, $projectionRepository);

        $eventConnection = new Connection(['url' => 'sqlite:///somedb.sqlite'], new Driver());
        $store = new MysqlSingleTableStore($eventConnection);
        $repository = new Repository($store, $eventStream, Profile::class);

        $store->prepare();

        $profile = Profile::create('1');
        $repository->save($profile);

        $result = $projectionConnection->fetchAssociative('SELECT * FROM profile WHERE id = 1');
    }
}
