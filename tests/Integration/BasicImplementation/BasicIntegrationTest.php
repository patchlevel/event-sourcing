<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BasicImplementation;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\EventBus\SymfonyEventBus;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AttributeAggregateRootRegistryFactory;
use Patchlevel\EventSourcing\Projection\Projection\Store\InMemoryStore;
use Patchlevel\EventSourcing\Projection\Projectionist\DefaultProjectionist;
use Patchlevel\EventSourcing\Projection\Projectionist\ProjectionistEventBusWrapper;
use Patchlevel\EventSourcing\Projection\Projector\InMemoryProjectorRepository;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Snapshot\Adapter\InMemorySnapshotAdapter;
use Patchlevel\EventSourcing\Snapshot\DefaultSnapshotStore;
use Patchlevel\EventSourcing\Store\DoctrineDbalStore;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Aggregate\Profile;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\MessageDecorator\FooMessageDecorator;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Processor\SendEmailProcessor;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Projection\ProfileProjection;
use Patchlevel\EventSourcing\Tests\Integration\DbalManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore as LockInMemoryStore;

/** @coversNothing */
final class BasicIntegrationTest extends TestCase
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
            (new AttributeAggregateRootRegistryFactory())->create([__DIR__ . '/Aggregate']),
            'eventstore',
        );

        $profileProjection = new ProfileProjection($this->connection);
        $projectorRepository = new InMemoryProjectorRepository(
            [$profileProjection],
        );

        $projectionist = new DefaultProjectionist(
            $store,
            new InMemoryStore(),
            $projectorRepository,
        );

        $innerEventStream = new DefaultEventBus();
        $innerEventStream->addListener(new SendEmailProcessor());

        $eventStream = new ProjectionistEventBusWrapper(
            $innerEventStream,
            $projectionist,
            new LockFactory(
                new LockInMemoryStore(),
            ),
        );

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => Profile::class]),
            $store,
            $eventStream,
            null,
            new FooMessageDecorator(),
        );
        $repository = $manager->get(Profile::class);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();
        $projectionist->boot();

        $profile = Profile::create(ProfileId::fromString('1'), 'John');
        $repository->save($profile);

        $result = $this->connection->fetchAssociative('SELECT * FROM projection_profile WHERE id = ?', ['1']);

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertSame('1', $result['id']);
        self::assertSame('John', $result['name']);

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => Profile::class]),
            $store,
            $eventStream,
        );
        $repository = $manager->get(Profile::class);
        $profile = $repository->load('1');

        self::assertInstanceOf(Profile::class, $profile);
        self::assertSame('1', $profile->aggregateRootId());
        self::assertSame(1, $profile->playhead());
        self::assertSame('John', $profile->name());
        self::assertSame(1, SendEmailMock::count());
    }

    public function testWithSymfonySuccessful(): void
    {
        $store = new DoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            (new AttributeAggregateRootRegistryFactory())->create([__DIR__ . '/Aggregate']),
            'eventstore',
        );

        $profileProjection = new ProfileProjection($this->connection);
        $projectorRepository = new InMemoryProjectorRepository(
            [$profileProjection],
        );

        $projectionist = new DefaultProjectionist(
            $store,
            new InMemoryStore(),
            $projectorRepository,
        );

        $innerEventStream = SymfonyEventBus::create([
            new SendEmailProcessor(),
        ]);

        $eventStream = new ProjectionistEventBusWrapper(
            $innerEventStream,
            $projectionist,
            new LockFactory(
                new LockInMemoryStore(),
            ),
        );

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => Profile::class]),
            $store,
            $eventStream,
        );

        $repository = $manager->get(Profile::class);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();
        $projectionist->boot();

        $profile = Profile::create(ProfileId::fromString('1'), 'John');
        $repository->save($profile);

        $result = $this->connection->fetchAssociative('SELECT * FROM projection_profile WHERE id = ?', ['1']);

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertSame('1', $result['id']);
        self::assertSame('John', $result['name']);

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => Profile::class]),
            $store,
            $eventStream,
            null,
            new FooMessageDecorator(),
        );
        $repository = $manager->get(Profile::class);

        $profile = $repository->load('1');

        self::assertInstanceOf(Profile::class, $profile);
        self::assertSame('1', $profile->aggregateRootId());
        self::assertSame(1, $profile->playhead());
        self::assertSame('John', $profile->name());
        self::assertSame(1, SendEmailMock::count());
    }

    public function testSnapshot(): void
    {
        $store = new DoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            (new AttributeAggregateRootRegistryFactory())->create([__DIR__ . '/Aggregate']),
            'eventstore',
        );

        $profileProjection = new ProfileProjection($this->connection);
        $projectorRepository = new InMemoryProjectorRepository(
            [$profileProjection],
        );

        $projectionist = new DefaultProjectionist(
            $store,
            new InMemoryStore(),
            $projectorRepository,
        );

        $innerEventStream = new DefaultEventBus();
        $innerEventStream->addListener(new SendEmailProcessor());

        $eventStream = new ProjectionistEventBusWrapper(
            $innerEventStream,
            $projectionist,
            new LockFactory(
                new LockInMemoryStore(),
            ),
        );

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => Profile::class]),
            $store,
            $eventStream,
            new DefaultSnapshotStore(['default' => new InMemorySnapshotAdapter()]),
            new FooMessageDecorator(),
        );
        $repository = $manager->get(Profile::class);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();
        $projectionist->boot();

        $profile = Profile::create(ProfileId::fromString('1'), 'John');
        $repository->save($profile);

        $result = $this->connection->fetchAssociative('SELECT * FROM projection_profile WHERE id = ?', ['1']);

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertSame('1', $result['id']);
        self::assertSame('John', $result['name']);

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => Profile::class]),
            $store,
            $eventStream,
        );
        $repository = $manager->get(Profile::class);
        $profile = $repository->load('1');

        self::assertInstanceOf(Profile::class, $profile);
        self::assertSame('1', $profile->aggregateRootId());
        self::assertSame(1, $profile->playhead());
        self::assertSame('John', $profile->name());
        self::assertSame(1, SendEmailMock::count());
    }
}
