<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\ChildAggregate;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Message\Serializer\DefaultHeadersSerializer;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Snapshot\Adapter\InMemorySnapshotAdapter;
use Patchlevel\EventSourcing\Snapshot\DefaultSnapshotStore;
use Patchlevel\EventSourcing\Store\DoctrineDbalStore;
use Patchlevel\EventSourcing\Subscription\Engine\DefaultSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Repository\RunSubscriptionEngineRepositoryManager;
use Patchlevel\EventSourcing\Subscription\Store\InMemorySubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Tests\DbalManager;
use Patchlevel\EventSourcing\Tests\Integration\ChildAggregate\Projection\ProfileProjector;
use PHPUnit\Framework\TestCase;

/** @coversNothing */
final class ChildAggregateIntegrationTest extends TestCase
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
        $store = new DoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
        );

        $profileProjector = new ProfileProjector($this->connection);

        $engine = new DefaultSubscriptionEngine(
            $store,
            new InMemorySubscriptionStore(),
            new MetadataSubscriberAccessorRepository([
                $profileProjector,
            ]),
        );

        $manager = new RunSubscriptionEngineRepositoryManager(
            new DefaultRepositoryManager(
                new AggregateRootRegistry(['profile' => Profile::class]),
                $store,
                null,
                null,
            ),
            $engine,
        );

        $repository = $manager->get(Profile::class);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();
        $engine->setup(skipBooting: true);

        $profileId = ProfileId::generate();
        $profile = Profile::create($profileId, 'John');
        $profile->changeName('Snow');
        $repository->save($profile);

        $result = $this->connection->fetchAssociative(
            'SELECT * FROM projection_profile WHERE id = ?',
            [$profileId->toString()],
        );

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertSame($profileId->toString(), $result['id']);
        self::assertSame('Snow', $result['name']);

        $repository = $manager->get(Profile::class);
        $profile = $repository->load($profileId);

        self::assertInstanceOf(Profile::class, $profile);
        self::assertEquals($profileId, $profile->aggregateRootId());
        self::assertSame(2, $profile->playhead());
        self::assertSame('Snow', $profile->name());
    }

    public function testSnapshot(): void
    {
        $store = new DoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
        );

        $profileProjection = new ProfileProjector($this->connection);

        $engine = new DefaultSubscriptionEngine(
            $store,
            new InMemorySubscriptionStore(),
            new MetadataSubscriberAccessorRepository([
                $profileProjection,
            ]),
        );

        $manager = new RunSubscriptionEngineRepositoryManager(
            new DefaultRepositoryManager(
                new AggregateRootRegistry(['profile' => Profile::class]),
                $store,
                null,
                new DefaultSnapshotStore(['default' => new InMemorySnapshotAdapter()]),
            ),
            $engine,
        );

        $repository = $manager->get(Profile::class);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();
        $engine->setup(skipBooting: true);

        $profileId = ProfileId::generate();
        $profile = Profile::create($profileId, 'John');
        $repository->save($profile);

        $result = $this->connection->fetchAssociative(
            'SELECT * FROM projection_profile WHERE id = ?',
            [$profileId->toString()],
        );

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertSame($profileId->toString(), $result['id']);
        self::assertSame('John', $result['name']);

        $repository = $manager->get(Profile::class);
        $profile = $repository->load($profileId);

        self::assertInstanceOf(Profile::class, $profile);
        self::assertEquals($profileId, $profile->aggregateRootId());
        self::assertSame(1, $profile->playhead());
        self::assertSame('John', $profile->name());
    }
}
