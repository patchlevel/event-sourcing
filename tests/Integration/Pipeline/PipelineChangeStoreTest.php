<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Pipeline;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\Pipeline\Middleware\ExcludeEventMiddleware;
use Patchlevel\EventSourcing\Pipeline\Middleware\RecalculatePlayheadMiddleware;
use Patchlevel\EventSourcing\Pipeline\Middleware\ReplaceEventMiddleware;
use Patchlevel\EventSourcing\Pipeline\Pipeline;
use Patchlevel\EventSourcing\Pipeline\Source\StoreSource;
use Patchlevel\EventSourcing\Pipeline\Target\StoreTarget;
use Patchlevel\EventSourcing\Repository\DefaultRepository;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaManager;
use Patchlevel\EventSourcing\Store\MultiTableStore;
use Patchlevel\EventSourcing\Store\SingleTableStore;
use Patchlevel\EventSourcing\Tests\Integration\DbalManager;
use Patchlevel\EventSourcing\Tests\Integration\Pipeline\Aggregate\Profile;
use Patchlevel\EventSourcing\Tests\Integration\Pipeline\Events\NewVisited;
use Patchlevel\EventSourcing\Tests\Integration\Pipeline\Events\OldVisited;
use Patchlevel\EventSourcing\Tests\Integration\Pipeline\Events\PrivacyAdded;
use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
final class PipelineChangeStoreTest extends TestCase
{
    private Connection $connectionOld;
    private Connection $connectionNew;

    public function setUp(): void
    {
        $this->connectionOld = DbalManager::createConnection();
        $this->connectionNew = DbalManager::createConnection('eventstore_new');
    }

    public function tearDown(): void
    {
        $this->connectionOld->close();
        $this->connectionNew->close();
    }

    public function testSuccessful(): void
    {
        $oldStore = new MultiTableStore(
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

        $oldRepository = new DefaultRepository($oldStore, new DefaultEventBus(), Profile::class);
        $newRepository = new DefaultRepository($newStore, new DefaultEventBus(), Profile::class);

        $profile = Profile::create('1');
        $profile->visit();
        $profile->privacy();
        $profile->visit();

        $oldRepository->save($profile);
        self::assertEquals(4, $oldStore->count());

        self::assertEquals('1', $profile->aggregateRootId());
        self::assertEquals(4, $profile->playhead());
        self::assertEquals(true, $profile->isPrivate());
        self::assertEquals(2, $profile->count());

        $pipeline = new Pipeline(
            new StoreSource($oldStore),
            new StoreTarget($newStore),
            [
                new ExcludeEventMiddleware([PrivacyAdded::class]),
                new ReplaceEventMiddleware(OldVisited::class, static function (OldVisited $oldVisited) {
                    return NewVisited::raise($oldVisited->profileId());
                }),
                new RecalculatePlayheadMiddleware(),
            ]
        );

        self::assertEquals(4, $pipeline->count());
        $pipeline->run();

        $newProfile = $newRepository->load('1');

        self::assertInstanceOf(Profile::class, $newProfile);
        self::assertEquals('1', $newProfile->aggregateRootId());
        self::assertEquals(3, $newProfile->playhead());
        self::assertEquals(false, $newProfile->isPrivate());
        self::assertEquals(-2, $newProfile->count());
    }
}
