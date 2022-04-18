<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Pipeline;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AttributeAggregateRootRegistryFactory;
use Patchlevel\EventSourcing\Pipeline\Middleware\ExcludeEventMiddleware;
use Patchlevel\EventSourcing\Pipeline\Middleware\RecalculatePlayheadMiddleware;
use Patchlevel\EventSourcing\Pipeline\Middleware\ReplaceEventMiddleware;
use Patchlevel\EventSourcing\Pipeline\Pipeline;
use Patchlevel\EventSourcing\Pipeline\Source\StoreSource;
use Patchlevel\EventSourcing\Pipeline\Target\StoreTarget;
use Patchlevel\EventSourcing\Repository\DefaultRepository;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaManager;
use Patchlevel\EventSourcing\Serializer\JsonSerializer;
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
        $serializer = JsonSerializer::createDefault([__DIR__ . '/Events']);
        $aggregateRootRegistry = (new AttributeAggregateRootRegistryFactory())->create([__DIR__ . '/Aggregate']);

        $oldStore = new MultiTableStore(
            $this->connectionOld,
            $serializer,
            $aggregateRootRegistry,
            'eventstore'
        );

        (new DoctrineSchemaManager())->create($oldStore);

        $newStore = new SingleTableStore(
            $this->connectionNew,
            $serializer,
            $aggregateRootRegistry,
            'eventstore'
        );

        (new DoctrineSchemaManager())->create($newStore);

        $oldRepository = new DefaultRepository($oldStore, new DefaultEventBus(), Profile::class);
        $newRepository = new DefaultRepository($newStore, new DefaultEventBus(), Profile::class);

        $profile = Profile::create(ProfileId::fromString('1'));
        $profile->visit();
        $profile->privacy();
        $profile->visit();

        $oldRepository->save($profile);
        self::assertSame(4, $oldStore->count());

        self::assertSame('1', $profile->aggregateRootId());
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
            ]
        );

        self::assertSame(4, $pipeline->count());
        $pipeline->run();

        $newProfile = $newRepository->load('1');

        self::assertInstanceOf(Profile::class, $newProfile);
        self::assertSame('1', $newProfile->aggregateRootId());
        self::assertSame(3, $newProfile->playhead());
        self::assertSame(false, $newProfile->isPrivate());
        self::assertSame(-2, $newProfile->count());
    }
}
