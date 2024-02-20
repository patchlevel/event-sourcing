<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Projectionist;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Clock\FrozenClock;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Projection\Projection\Projection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionStatus;
use Patchlevel\EventSourcing\Projection\Projection\RunMode;
use Patchlevel\EventSourcing\Projection\Projection\Store\DoctrineStore;
use Patchlevel\EventSourcing\Projection\Projectionist\DefaultProjectionist;
use Patchlevel\EventSourcing\Projection\Projectionist\ProjectionistCriteria;
use Patchlevel\EventSourcing\Projection\RetryStrategy\DefaultRetryStrategy;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Schema\ChainSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\DoctrineDbalStore;
use Patchlevel\EventSourcing\Tests\Integration\DbalManager;
use Patchlevel\EventSourcing\Tests\Integration\Projectionist\Aggregate\Profile;
use Patchlevel\EventSourcing\Tests\Integration\Projectionist\Projection\ErrorProducerProjector;
use Patchlevel\EventSourcing\Tests\Integration\Projectionist\Projection\ProfileProjector;
use PHPUnit\Framework\TestCase;

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

        $projectionist = new DefaultProjectionist(
            $store,
            $projectionStore,
            [new ProfileProjector($this->projectionConnection)],
        );

        self::assertEquals(
            [new Projection('profile_1')],
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
                ),
            ],
            $projectionist->projections(),
        );

        $result = $this->projectionConnection->fetchAssociative('SELECT * FROM projection_profile_1 WHERE id = ?', ['1']);

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
        $store = new DoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            'eventstore',
        );

        $projectionStore = new DoctrineStore($this->connection);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            new ChainSchemaConfigurator([
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
        $clock = new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00'));

        $projectionist = new DefaultProjectionist(
            $store,
            $projectionStore,
            [$projector],
            new DefaultRetryStrategy(
                $clock,
                DefaultRetryStrategy::DEFAULT_BASE_DELAY,
                DefaultRetryStrategy::DEFAULT_DELAY_FACTOR,
                2,
            ),
        );

        $projectionist->boot();

        $projection = self::findProjection($projectionist->projections(), 'error_producer');

        self::assertEquals(ProjectionStatus::Active, $projection->status());
        self::assertEquals(null, $projection->projectionError());
        self::assertEquals(null, $projection->retry());

        $repository = $manager->get(Profile::class);

        $profile = Profile::create(ProfileId::fromString('1'), 'John');
        $repository->save($profile);

        $projector->subscribeError = true;
        $projectionist->run();

        $projection = self::findProjection($projectionist->projections(), 'error_producer');

        self::assertEquals(ProjectionStatus::Error, $projection->status());
        self::assertEquals('subscribe error', $projection->projectionError()?->errorMessage);
        self::assertEquals(ProjectionStatus::Active, $projection->projectionError()?->previousStatus);
        self::assertEquals(1, $projection->retry()?->attempt);
        self::assertEquals(new DateTimeImmutable('2021-01-01T00:00:10'), $projection->retry()?->nextRetry);

        $projectionist->run();

        $projection = self::findProjection($projectionist->projections(), 'error_producer');

        self::assertEquals(ProjectionStatus::Error, $projection->status());
        self::assertEquals('subscribe error', $projection->projectionError()?->errorMessage);
        self::assertEquals(ProjectionStatus::Active, $projection->projectionError()?->previousStatus);
        self::assertEquals(1, $projection->retry()?->attempt);
        self::assertEquals(new DateTimeImmutable('2021-01-01T00:00:10'), $projection->retry()?->nextRetry);

        $clock->sleep(10);

        $projectionist->run();

        $projection = self::findProjection($projectionist->projections(), 'error_producer');

        self::assertEquals(ProjectionStatus::Error, $projection->status());
        self::assertEquals('subscribe error', $projection->projectionError()?->errorMessage);
        self::assertEquals(ProjectionStatus::Active, $projection->projectionError()?->previousStatus);
        self::assertEquals(2, $projection->retry()?->attempt);
        self::assertEquals(new DateTimeImmutable('2021-01-01T00:00:20'), $projection->retry()?->nextRetry);

        $clock->sleep(20);

        $projectionist->run();

        $projection = self::findProjection($projectionist->projections(), 'error_producer');

        self::assertEquals(ProjectionStatus::Error, $projection->status());
        self::assertEquals('subscribe error', $projection->projectionError()?->errorMessage);
        self::assertEquals(ProjectionStatus::Active, $projection->projectionError()?->previousStatus);
        self::assertEquals(null, $projection->retry());

        $projectionist->reactivate(new ProjectionistCriteria(
            ids: ['error_producer'],
        ));

        $projection = self::findProjection($projectionist->projections(), 'error_producer');

        self::assertEquals(ProjectionStatus::Active, $projection->status());
        self::assertEquals(null, $projection->projectionError());
        self::assertEquals(null, $projection->retry());

        $projectionist->run();

        $projection = self::findProjection($projectionist->projections(), 'error_producer');

        self::assertEquals(ProjectionStatus::Error, $projection->status());
        self::assertEquals('subscribe error', $projection->projectionError()?->errorMessage);
        self::assertEquals(ProjectionStatus::Active, $projection->projectionError()?->previousStatus);
        self::assertEquals(1, $projection->retry()?->attempt);
        self::assertEquals(new DateTimeImmutable('2021-01-01T00:00:40'), $projection->retry()?->nextRetry);

        $clock->sleep(10);
        $projector->subscribeError = false;

        $projectionist->run();

        $projection = self::findProjection($projectionist->projections(), 'error_producer');

        self::assertEquals(ProjectionStatus::Active, $projection->status());
        self::assertEquals(null, $projection->projectionError());
        self::assertEquals(null, $projection->retry());
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
