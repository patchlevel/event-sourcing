<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Projectionist;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Clock\FrozenClock;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\EventBus\Serializer\DefaultHeadersSerializer;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Projection\Projection\Projection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionStatus;
use Patchlevel\EventSourcing\Projection\Projection\RunMode;
use Patchlevel\EventSourcing\Projection\Projection\Store\DoctrineStore;
use Patchlevel\EventSourcing\Projection\Projectionist\DefaultProjectionist;
use Patchlevel\EventSourcing\Projection\Projectionist\ProjectionistCriteria;
use Patchlevel\EventSourcing\Projection\Projector\MetadataProjectorAccessorRepository;
use Patchlevel\EventSourcing\Projection\Projector\TraceableProjectorAccessorRepository;
use Patchlevel\EventSourcing\Projection\RetryStrategy\ClockBasedRetryStrategy;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Repository\MessageDecorator\TraceDecorator;
use Patchlevel\EventSourcing\Repository\MessageDecorator\TraceHeader;
use Patchlevel\EventSourcing\Repository\MessageDecorator\TraceStack;
use Patchlevel\EventSourcing\Schema\ChainDoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\DoctrineDbalStore;
use Patchlevel\EventSourcing\Tests\DbalManager;
use Patchlevel\EventSourcing\Tests\Integration\Projectionist\Aggregate\Profile;
use Patchlevel\EventSourcing\Tests\Integration\Projectionist\Projection\ErrorProducerProjector;
use Patchlevel\EventSourcing\Tests\Integration\Projectionist\Projection\ProfileProcessor;
use Patchlevel\EventSourcing\Tests\Integration\Projectionist\Projection\ProfileProjector;
use PHPUnit\Framework\TestCase;

use function iterator_to_array;

/** @coversNothing */
final class ProjectionistTest extends TestCase
{
    private Connection $connection;
    private Connection $projectionConnection;

    public function setUp(): void
    {
        $this->connection = DbalManager::createConnection();
        $this->projectionConnection = DbalManager::createConnection();
    }

    public function tearDown(): void
    {
        $this->connection->close();
        $this->projectionConnection->close();
    }

    public function testHappyPath(): void
    {
        $store = new DoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            DefaultHeadersSerializer::createFromPaths([
                __DIR__ . '/../../../src',
                __DIR__,
            ]),
            'eventstore',
        );

        $clock = new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00'));

        $projectionStore = new DoctrineStore(
            $this->connection,
            $clock,
        );

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => Profile::class]),
            $store,
            DefaultEventBus::create(),
        );

        $repository = $manager->get(Profile::class);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            new ChainDoctrineSchemaConfigurator([
                $store,
                $projectionStore,
            ]),
        );

        $schemaDirector->create();

        $projectionist = new DefaultProjectionist(
            $store,
            $projectionStore,
            new MetadataProjectorAccessorRepository([new ProfileProjector($this->projectionConnection)]),
        );

        self::assertEquals(
            [new Projection('profile_1', lastSavedAt: new DateTimeImmutable('2021-01-01T00:00:00'))],
            $projectionist->projections(),
        );

        $projectionist->boot();

        self::assertEquals(
            [
                new Projection(
                    'profile_1',
                    Projection::DEFAULT_GROUP,
                    RunMode::FromBeginning,
                    ProjectionStatus::Active,
                    lastSavedAt: new DateTimeImmutable('2021-01-01T00:00:00'),
                ),
            ],
            $projectionist->projections(),
        );

        $profile = Profile::create(ProfileId::fromString('1'), 'John');
        $repository->save($profile);

        $projectionist->run();

        self::assertEquals(
            [
                new Projection(
                    'profile_1',
                    Projection::DEFAULT_GROUP,
                    RunMode::FromBeginning,
                    ProjectionStatus::Active,
                    1,
                    lastSavedAt: new DateTimeImmutable('2021-01-01T00:00:00'),
                ),
            ],
            $projectionist->projections(),
        );

        $result = $this->projectionConnection->fetchAssociative(
            'SELECT * FROM projection_profile_1 WHERE id = ?',
            ['1'],
        );

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertSame('1', $result['id']);
        self::assertSame('John', $result['name']);

        $projectionist->remove();

        self::assertEquals(
            [
                new Projection(
                    'profile_1',
                    Projection::DEFAULT_GROUP,
                    RunMode::FromBeginning,
                    ProjectionStatus::New,
                    lastSavedAt: new DateTimeImmutable('2021-01-01T00:00:00'),
                ),
            ],
            $projectionist->projections(),
        );

        self::assertFalse(
            $this->projectionConnection->createSchemaManager()->tableExists('projection_profile_1'),
        );
    }

    public function testErrorHandling(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00'));

        $store = new DoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            DefaultHeadersSerializer::createFromPaths([
                __DIR__ . '/../../../src',
                __DIR__,
            ]),
            'eventstore',
        );

        $projectionStore = new DoctrineStore(
            $this->connection,
            $clock,
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            new ChainDoctrineSchemaConfigurator([
                $store,
                $projectionStore,
            ]),
        );

        $schemaDirector->create();

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => Profile::class]),
            $store,
            DefaultEventBus::create(),
        );

        $projector = new ErrorProducerProjector();

        $projectionist = new DefaultProjectionist(
            $store,
            $projectionStore,
            new MetadataProjectorAccessorRepository([$projector]),
            new ClockBasedRetryStrategy(
                $clock,
                ClockBasedRetryStrategy::DEFAULT_BASE_DELAY,
                ClockBasedRetryStrategy::DEFAULT_DELAY_FACTOR,
                2,
            ),
        );

        $projectionist->boot();

        $projection = self::findProjection($projectionist->projections(), 'error_producer');

        self::assertEquals(ProjectionStatus::Active, $projection->status());
        self::assertEquals(null, $projection->projectionError());
        self::assertEquals(0, $projection->retryAttempt());

        $repository = $manager->get(Profile::class);

        $profile = Profile::create(ProfileId::fromString('1'), 'John');
        $repository->save($profile);

        $projector->subscribeError = true;
        $projectionist->run();

        $projection = self::findProjection($projectionist->projections(), 'error_producer');

        self::assertEquals(ProjectionStatus::Error, $projection->status());
        self::assertEquals('subscribe error', $projection->projectionError()?->errorMessage);
        self::assertEquals(ProjectionStatus::Active, $projection->projectionError()?->previousStatus);
        self::assertEquals(0, $projection->retryAttempt());

        $projectionist->run();

        $projection = self::findProjection($projectionist->projections(), 'error_producer');

        self::assertEquals(ProjectionStatus::Error, $projection->status());
        self::assertEquals('subscribe error', $projection->projectionError()?->errorMessage);
        self::assertEquals(ProjectionStatus::Active, $projection->projectionError()?->previousStatus);
        self::assertEquals(0, $projection->retryAttempt());

        $clock->sleep(5);

        $projectionist->run();

        $projection = self::findProjection($projectionist->projections(), 'error_producer');

        self::assertEquals(ProjectionStatus::Error, $projection->status());
        self::assertEquals('subscribe error', $projection->projectionError()?->errorMessage);
        self::assertEquals(ProjectionStatus::Active, $projection->projectionError()?->previousStatus);
        self::assertEquals(1, $projection->retryAttempt());

        $clock->sleep(10);

        $projectionist->run();

        $projection = self::findProjection($projectionist->projections(), 'error_producer');

        self::assertEquals(ProjectionStatus::Error, $projection->status());
        self::assertEquals('subscribe error', $projection->projectionError()?->errorMessage);
        self::assertEquals(ProjectionStatus::Active, $projection->projectionError()?->previousStatus);
        self::assertEquals(2, $projection->retryAttempt());

        $projectionist->reactivate(new ProjectionistCriteria(
            ids: ['error_producer'],
        ));

        $projection = self::findProjection($projectionist->projections(), 'error_producer');

        self::assertEquals(ProjectionStatus::Active, $projection->status());
        self::assertEquals(null, $projection->projectionError());
        self::assertEquals(0, $projection->retryAttempt());

        $projectionist->run();

        $projection = self::findProjection($projectionist->projections(), 'error_producer');

        self::assertEquals(ProjectionStatus::Error, $projection->status());
        self::assertEquals('subscribe error', $projection->projectionError()?->errorMessage);
        self::assertEquals(ProjectionStatus::Active, $projection->projectionError()?->previousStatus);
        self::assertEquals(0, $projection->retryAttempt());

        $clock->sleep(5);
        $projector->subscribeError = false;

        $projectionist->run();

        $projection = self::findProjection($projectionist->projections(), 'error_producer');

        self::assertEquals(ProjectionStatus::Active, $projection->status());
        self::assertEquals(null, $projection->projectionError());
        self::assertEquals(0, $projection->retryAttempt());
    }

    public function testProcessor(): void
    {
        $store = new DoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            DefaultHeadersSerializer::createFromPaths([
                __DIR__ . '/../../../src',
                __DIR__,
            ]),
            'eventstore',
        );

        $clock = new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00'));

        $projectionStore = new DoctrineStore(
            $this->connection,
            $clock,
        );

        $traceStack = new TraceStack();

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => Profile::class]),
            $store,
            DefaultEventBus::create(),
            null,
            new TraceDecorator($traceStack),
        );

        $projectorAccessorRepository = new TraceableProjectorAccessorRepository(
            new MetadataProjectorAccessorRepository([new ProfileProcessor($manager)]),
            $traceStack,
        );

        $repository = $manager->get(Profile::class);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            new ChainDoctrineSchemaConfigurator([
                $store,
                $projectionStore,
            ]),
        );

        $schemaDirector->create();

        $projectionist = new DefaultProjectionist(
            $store,
            $projectionStore,
            $projectorAccessorRepository,
        );

        self::assertEquals(
            [new Projection('profile', lastSavedAt: new DateTimeImmutable('2021-01-01T00:00:00'))],
            $projectionist->projections(),
        );

        $projectionist->boot();

        self::assertEquals(
            [
                new Projection(
                    'profile',
                    Projection::DEFAULT_GROUP,
                    RunMode::FromBeginning,
                    ProjectionStatus::Active,
                    lastSavedAt: new DateTimeImmutable('2021-01-01T00:00:00'),
                ),
            ],
            $projectionist->projections(),
        );

        $profile = Profile::create(ProfileId::fromString('1'), 'John');
        $repository->save($profile);

        $projectionist->run();

        $projections = $projectionist->projections();

        self::assertCount(1, $projections);
        self::assertArrayHasKey(0, $projections);

        $projection = $projections[0];

        self::assertEquals('profile', $projection->id());

        self::assertEquals(ProjectionStatus::Active, $projection->status());

        /** @var list<Message> $messages */
        $messages = iterator_to_array($store->load());

        self::assertCount(2, $messages);
        self::assertArrayHasKey(1, $messages);

        self::assertEquals(
            new TraceHeader([
                [
                    'name' => 'profile',
                    'category' => 'event_sourcing/projector/default',
                ],
            ]),
            $messages[1]->header(TraceHeader::class),
        );
    }

    /** @param list<Projection> $projections */
    private static function findProjection(array $projections, string $id): Projection
    {
        foreach ($projections as $projection) {
            if ($projection->id() === $id) {
                return $projection;
            }
        }

        self::fail('projection not found');
    }
}
