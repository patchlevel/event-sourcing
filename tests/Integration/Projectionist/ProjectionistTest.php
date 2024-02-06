<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Projectionist;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\Lock\DoctrineDbalStoreSchemaAdapter;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AttributeAggregateRootRegistryFactory;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionCriteria;
use Patchlevel\EventSourcing\Projection\Projection\Store\DoctrineStore;
use Patchlevel\EventSourcing\Projection\Projectionist\DefaultProjectionist;
use Patchlevel\EventSourcing\Projection\Projectionist\SyncProjectionistEventBusWrapper;
use Patchlevel\EventSourcing\Projection\Projector\InMemoryProjectorRepository;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Schema\ChainSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\DoctrineDbalStore;
use Patchlevel\EventSourcing\Tests\Integration\DbalManager;
use Patchlevel\EventSourcing\Tests\Integration\Projectionist\Aggregate\Profile;
use Patchlevel\EventSourcing\Tests\Integration\Projectionist\Projection\ProfileProjector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\DoctrineDbalStore as LockStore;

/** @coversNothing */
final class ProjectionistTest extends TestCase
{
    private Connection $connection;

    public function setUp(): void
    {
        $this->connection = DbalManager::createConnection();
    }

    public function tearDown(): void
    {
        $this->connection->close();
    }

    public function testAsync(): void
    {
        $store = new DoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            'eventstore',
        );

        $projectionStore = new DoctrineStore($this->connection);

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => Profile::class]),
            $store,
            DefaultEventBus::create(),
        );

        $repository = $manager->get(Profile::class);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            new ChainSchemaConfigurator([
                $store,
                $projectionStore,
            ]),
        );

        $schemaDirector->create();

        $profile = Profile::create(ProfileId::fromString('1'), 'John');
        $repository->save($profile);

        $projectionist = new DefaultProjectionist(
            $store,
            $projectionStore,
            new InMemoryProjectorRepository(
                [new ProfileProjector($this->connection)],
            ),
        );

        $projectionist->boot();
        $projectionist->run();

        $result = $this->connection->fetchAssociative('SELECT * FROM projection_profile_1 WHERE id = ?', ['1']);

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertSame('1', $result['id']);
        self::assertSame('John', $result['name']);

        $projectionist->remove();
    }

    public function testSync(): void
    {
        $aggregateRegistry = (new AttributeAggregateRootRegistryFactory())->create([__DIR__ . '/Aggregate']);

        $store = new DoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            'eventstore',
        );

        $lockStore = new LockStore($this->connection);
        $projectionStore = new DoctrineStore($this->connection);

        $projectionist = new DefaultProjectionist(
            $store,
            $projectionStore,
            new InMemoryProjectorRepository(
                [new ProfileProjector($this->connection)],
            ),
        );

        $manager = new DefaultRepositoryManager(
            $aggregateRegistry,
            $store,
            new SyncProjectionistEventBusWrapper(
                DefaultEventBus::create(),
                $projectionist,
                new LockFactory($lockStore),
            ),
        );

        $repository = $manager->get(Profile::class);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            new ChainSchemaConfigurator([
                $store,
                $projectionStore,
                new DoctrineDbalStoreSchemaAdapter($lockStore),
            ]),
        );

        $schemaDirector->drop();
        $schemaDirector->create();
        $projectionist->boot(new ProjectionCriteria(), null, true);

        $profile = Profile::create(ProfileId::fromString('1'), 'John');
        $repository->save($profile);

        $projectionist->run(new ProjectionCriteria(), null, true);

        $result = $this->connection->fetchAssociative('SELECT * FROM projection_profile_1 WHERE id = ?', ['1']);

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertSame('1', $result['id']);
        self::assertSame('John', $result['name']);

        $projectionist->remove();
    }
}
