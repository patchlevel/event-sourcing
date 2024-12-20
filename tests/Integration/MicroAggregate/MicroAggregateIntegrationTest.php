<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\MicroAggregate;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Snapshot\Adapter\InMemorySnapshotAdapter;
use Patchlevel\EventSourcing\Snapshot\DefaultSnapshotStore;
use Patchlevel\EventSourcing\Store\StreamDoctrineDbalStore;
use Patchlevel\EventSourcing\Subscription\Engine\DefaultSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\ThrowOnErrorSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Repository\RunSubscriptionEngineRepositoryManager;
use Patchlevel\EventSourcing\Subscription\Store\InMemorySubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Tests\DbalManager;
use Patchlevel\EventSourcing\Tests\Integration\MicroAggregate\Projection\ProfileProjector;
use PHPUnit\Framework\TestCase;

/** @coversNothing */
final class MicroAggregateIntegrationTest extends TestCase
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
        $store = new StreamDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
        );

        $profileProjector = new ProfileProjector($this->connection);

        $engine = new ThrowOnErrorSubscriptionEngine(new DefaultSubscriptionEngine(
            $store,
            new InMemorySubscriptionStore(),
            new MetadataSubscriberAccessorRepository([$profileProjector]),
        ));

        $manager = new RunSubscriptionEngineRepositoryManager(
            new DefaultRepositoryManager(
                new AggregateRootRegistry([
                    'profile' => Profile::class,
                    'personal_information' => PersonalInformation::class,
                ]),
                $store,
                null,
                null,
            ),
            $engine,
        );

        $profileRepository = $manager->get(Profile::class);
        $personalInformationRepository = $manager->get(PersonalInformation::class);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();
        $engine->setup(skipBooting: true);

        $profileId = ProfileId::generate();
        $profile = Profile::create($profileId, 'John');
        $profileRepository->save($profile);

        $personalInformation = $personalInformationRepository->load($profileId);
        $personalInformation->changeName('Snow');
        $personalInformationRepository->save($personalInformation);

        $result = $this->connection->fetchAssociative(
            'SELECT * FROM projection_profile WHERE id = ?',
            [$profileId->toString()],
        );

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertSame($profileId->toString(), $result['id']);
        self::assertSame('Snow', $result['name']);

        $profile = $profileRepository->load($profileId);
        $personalInformation = $personalInformationRepository->load($profileId);

        self::assertInstanceOf(Profile::class, $profile);
        self::assertEquals($profileId, $profile->aggregateRootId());
        self::assertSame(2, $profile->playhead());
        self::assertSame('Snow', $personalInformation->name());
    }

    public function testSnapshot(): void
    {
        $store = new StreamDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
        );

        $profileProjection = new ProfileProjector($this->connection);

        $engine = new DefaultSubscriptionEngine(
            $store,
            new InMemorySubscriptionStore(),
            new MetadataSubscriberAccessorRepository([$profileProjection]),
        );

        $manager = new RunSubscriptionEngineRepositoryManager(
            new DefaultRepositoryManager(
                new AggregateRootRegistry([
                    'profile' => Profile::class,
                    'personal_information' => PersonalInformation::class,
                ]),
                $store,
                null,
                new DefaultSnapshotStore(['default' => new InMemorySnapshotAdapter()]),
            ),
            $engine,
        );

        $profileRepository = $manager->get(Profile::class);
        $personalInformationRepository = $manager->get(PersonalInformation::class);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();
        $engine->setup(skipBooting: true);

        $profileId = ProfileId::generate();
        $profile = Profile::create($profileId, 'John');
        $profileRepository->save($profile);

        $result = $this->connection->fetchAssociative(
            'SELECT * FROM projection_profile WHERE id = ?',
            [$profileId->toString()],
        );

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertSame($profileId->toString(), $result['id']);
        self::assertSame('John', $result['name']);

        // create snapshot
        $profileRepository->load($profileId);
        $personalInformationRepository->load($profileId);

        // load from snapshot
        $personalInformation = $personalInformationRepository->load($profileId);

        $personalInformation->changeName('Snow');
        $personalInformationRepository->save($personalInformation);

        $profile = $profileRepository->load($profileId);
        $personalInformation = $personalInformationRepository->load($profileId);

        self::assertInstanceOf(Profile::class, $profile);
        self::assertEquals($profileId, $profile->aggregateRootId());
        self::assertSame(2, $profile->playhead());
        self::assertSame('Snow', $personalInformation->name());
    }
}
