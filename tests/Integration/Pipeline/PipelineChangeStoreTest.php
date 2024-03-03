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
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\DoctrineDbalStore;
use Patchlevel\EventSourcing\Tests\DbalManager;
use Patchlevel\EventSourcing\Tests\Integration\Pipeline\Aggregate\Profile;
use Patchlevel\EventSourcing\Tests\Integration\Pipeline\Events\NewVisited;
use Patchlevel\EventSourcing\Tests\Integration\Pipeline\Events\OldVisited;
use Patchlevel\EventSourcing\Tests\Integration\Pipeline\Events\PrivacyAdded;
use PHPUnit\Framework\TestCase;

/** @coversNothing */
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
        $serializer = DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']);

        $oldStore = new DoctrineDbalStore(
            $this->connectionOld,
            $serializer,
            'eventstore',
        );

        $oldSchemaDirector = new DoctrineSchemaDirector(
            $this->connectionOld,
            $oldStore,
        );

        $oldSchemaDirector->create();

        $newStore = new DoctrineDbalStore(
            $this->connectionNew,
            $serializer,
            'eventstore',
        );

        $newSchemaDirector = new DoctrineSchemaDirector(
            $this->connectionNew,
            $newStore,
        );

        $newSchemaDirector->create();

        $oldRepository = new DefaultRepository($oldStore, DefaultEventBus::create(), Profile::metadata());
        $newRepository = new DefaultRepository($newStore, DefaultEventBus::create(), Profile::metadata());

        $profileId = ProfileId::fromString('1');
        $profile = Profile::create($profileId);
        $profile->visit();
        $profile->privacy();
        $profile->visit();

        $oldRepository->save($profile);
        self::assertSame(4, $oldStore->count());

        self::assertEquals($profileId, $profile->aggregateRootId());
        self::assertSame(4, $profile->playhead());
        self::assertSame(true, $profile->isPrivate());
        self::assertSame(2, $profile->count());

        $pipeline = new Pipeline(
            new StoreSource($oldStore),
            new StoreTarget($newStore),
            [
                new ExcludeEventMiddleware([PrivacyAdded::class]),
                new ReplaceEventMiddleware(OldVisited::class, static function (OldVisited $oldVisited) {
                    return new NewVisited($oldVisited->profileId);
                }),
                new RecalculatePlayheadMiddleware(),
            ],
        );

        self::assertSame(4, $pipeline->count());
        $pipeline->run();

        $newProfile = $newRepository->load($profileId);

        self::assertInstanceOf(Profile::class, $newProfile);
        self::assertEquals($profileId, $newProfile->aggregateRootId());
        self::assertSame(3, $newProfile->playhead());
        self::assertSame(false, $newProfile->isPrivate());
        self::assertSame(-2, $newProfile->count());
    }
}
