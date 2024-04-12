<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Clock\FrozenClock;
use Patchlevel\EventSourcing\Debug\Trace\TraceableSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Debug\Trace\TraceDecorator;
use Patchlevel\EventSourcing\Debug\Trace\TraceHeader;
use Patchlevel\EventSourcing\Debug\Trace\TraceStack;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Schema\ChainDoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\DoctrineDbalStore;
use Patchlevel\EventSourcing\Subscription\Engine\DefaultSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\ClockBasedRetryStrategy;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\DoctrineSubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Tests\DbalManager;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Aggregate\Profile;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber\ErrorProducerSubscriber;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber\ProfileNewProjection;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber\ProfileProcessor;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber\ProfileProjection;
use PHPUnit\Framework\TestCase;

use function gc_collect_cycles;
use function iterator_to_array;

/** @coversNothing */
final class SubscriptionTest extends TestCase
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

        gc_collect_cycles();
    }

    public function testHappyPath(): void
    {
        $store = new DoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
        );

        $clock = new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00'));

        $subscriptionStore = new DoctrineSubscriptionStore(
            $this->connection,
            $clock,
        );

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => Profile::class]),
            $store,
        );

        $repository = $manager->get(Profile::class);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            new ChainDoctrineSchemaConfigurator([
                $store,
                $subscriptionStore,
            ]),
        );

        $schemaDirector->create();

        $engine = new DefaultSubscriptionEngine(
            $store,
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([new ProfileProjection($this->projectionConnection)]),
        );

        self::assertEquals(
            [
                new Subscription(
                    'profile_1',
                    'projector',
                    lastSavedAt: new DateTimeImmutable('2021-01-01T00:00:00'),
                ),
            ],
            $engine->subscriptions(),
        );

        $result = $engine->setup();

        self::assertEquals([], $result->errors);

        $result = $engine->boot();

        self::assertEquals(0, $result->processedMessages);
        self::assertEquals([], $result->errors);

        self::assertEquals(
            [
                new Subscription(
                    'profile_1',
                    'projector',
                    RunMode::FromBeginning,
                    Status::Active,
                    lastSavedAt: new DateTimeImmutable('2021-01-01T00:00:00'),
                ),
            ],
            $engine->subscriptions(),
        );

        $profile = Profile::create(ProfileId::fromString('1'), 'John');
        $repository->save($profile);

        $result = $engine->run();

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals([], $result->errors);

        self::assertEquals(
            [
                new Subscription(
                    'profile_1',
                    'projector',
                    RunMode::FromBeginning,
                    Status::Active,
                    1,
                    lastSavedAt: new DateTimeImmutable('2021-01-01T00:00:00'),
                ),
            ],
            $engine->subscriptions(),
        );

        $result = $this->projectionConnection->fetchAssociative(
            'SELECT * FROM projection_profile_1 WHERE id = ?',
            ['1'],
        );

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertSame('1', $result['id']);
        self::assertSame('John', $result['name']);

        $result = $engine->remove();
        self::assertEquals([], $result->errors);

        self::assertEquals(
            [
                new Subscription(
                    'profile_1',
                    'projector',
                    RunMode::FromBeginning,
                    Status::New,
                    lastSavedAt: new DateTimeImmutable('2021-01-01T00:00:00'),
                ),
            ],
            $engine->subscriptions(),
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
        );

        $subscriptionStore = new DoctrineSubscriptionStore(
            $this->connection,
            $clock,
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            new ChainDoctrineSchemaConfigurator([
                $store,
                $subscriptionStore,
            ]),
        );

        $schemaDirector->create();

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => Profile::class]),
            $store,
        );

        $subscriber = new ErrorProducerSubscriber();

        $engine = new DefaultSubscriptionEngine(
            $store,
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            new ClockBasedRetryStrategy(
                $clock,
                ClockBasedRetryStrategy::DEFAULT_BASE_DELAY,
                ClockBasedRetryStrategy::DEFAULT_DELAY_FACTOR,
                2,
            ),
        );

        $result = $engine->setup();
        self::assertEquals([], $result->errors);

        $result = $engine->boot();
        self::assertEquals(0, $result->processedMessages);
        self::assertEquals([], $result->errors);

        $subscription = self::findSubscription($engine->subscriptions(), 'error_producer');

        self::assertEquals(Status::Active, $subscription->status());
        self::assertEquals(null, $subscription->subscriptionError());
        self::assertEquals(0, $subscription->retryAttempt());

        $repository = $manager->get(Profile::class);

        $profile = Profile::create(ProfileId::fromString('1'), 'John');
        $repository->save($profile);

        $subscriber->subscribeError = true;

        $result = $engine->run();

        self::assertEquals(1, $result->processedMessages);
        self::assertCount(1, $result->errors);

        $error = $result->errors[0];

        self::assertEquals('error_producer', $error->subscriptionId);
        self::assertEquals('subscribe error', $error->message);

        $subscription = self::findSubscription($engine->subscriptions(), 'error_producer');

        self::assertEquals(Status::Error, $subscription->status());
        self::assertEquals('subscribe error', $subscription->subscriptionError()?->errorMessage);
        self::assertEquals(Status::Active, $subscription->subscriptionError()?->previousStatus);
        self::assertEquals(0, $subscription->retryAttempt());

        $result = $engine->run();

        self::assertEquals(0, $result->processedMessages);
        self::assertEquals([], $result->errors);

        $subscription = self::findSubscription($engine->subscriptions(), 'error_producer');

        self::assertEquals(Status::Error, $subscription->status());
        self::assertEquals('subscribe error', $subscription->subscriptionError()?->errorMessage);
        self::assertEquals(Status::Active, $subscription->subscriptionError()?->previousStatus);
        self::assertEquals(0, $subscription->retryAttempt());

        $clock->sleep(5);

        $result = $engine->run();

        self::assertEquals(1, $result->processedMessages);
        self::assertCount(1, $result->errors);

        $error = $result->errors[0];

        self::assertEquals('error_producer', $error->subscriptionId);
        self::assertEquals('subscribe error', $error->message);

        $subscription = self::findSubscription($engine->subscriptions(), 'error_producer');

        self::assertEquals(Status::Error, $subscription->status());
        self::assertEquals('subscribe error', $subscription->subscriptionError()?->errorMessage);
        self::assertEquals(Status::Active, $subscription->subscriptionError()?->previousStatus);
        self::assertEquals(1, $subscription->retryAttempt());

        $clock->sleep(10);

        $result = $engine->run();

        self::assertEquals(1, $result->processedMessages);
        self::assertCount(1, $result->errors);

        $error = $result->errors[0];

        self::assertEquals('error_producer', $error->subscriptionId);
        self::assertEquals('subscribe error', $error->message);

        $subscription = self::findSubscription($engine->subscriptions(), 'error_producer');

        self::assertEquals(Status::Error, $subscription->status());
        self::assertEquals('subscribe error', $subscription->subscriptionError()?->errorMessage);
        self::assertEquals(Status::Active, $subscription->subscriptionError()?->previousStatus);
        self::assertEquals(2, $subscription->retryAttempt());

        $engine->reactivate(new SubscriptionEngineCriteria(
            ids: ['error_producer'],
        ));

        $subscription = self::findSubscription($engine->subscriptions(), 'error_producer');

        self::assertEquals(Status::Active, $subscription->status());
        self::assertEquals(null, $subscription->subscriptionError());
        self::assertEquals(0, $subscription->retryAttempt());

        $result = $engine->run();

        self::assertEquals(1, $result->processedMessages);
        self::assertCount(1, $result->errors);

        $error = $result->errors[0];

        self::assertEquals('error_producer', $error->subscriptionId);
        self::assertEquals('subscribe error', $error->message);

        $subscription = self::findSubscription($engine->subscriptions(), 'error_producer');

        self::assertEquals(Status::Error, $subscription->status());
        self::assertEquals('subscribe error', $subscription->subscriptionError()?->errorMessage);
        self::assertEquals(Status::Active, $subscription->subscriptionError()?->previousStatus);
        self::assertEquals(0, $subscription->retryAttempt());

        $clock->sleep(5);
        $subscriber->subscribeError = false;

        $result = $engine->run();

        self::assertEquals(1, $result->processedMessages);
        self::assertEquals([], $result->errors);

        $subscription = self::findSubscription($engine->subscriptions(), 'error_producer');

        self::assertEquals(Status::Active, $subscription->status());
        self::assertEquals(null, $subscription->subscriptionError());
        self::assertEquals(0, $subscription->retryAttempt());
    }

    public function testProcessor(): void
    {
        $store = new DoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
        );

        $clock = new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00'));

        $subscriptionStore = new DoctrineSubscriptionStore(
            $this->connection,
            $clock,
        );

        $traceStack = new TraceStack();

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => Profile::class]),
            $store,
            null,
            null,
            new TraceDecorator($traceStack),
        );

        $subscriberAccessorRepository = new TraceableSubscriberAccessorRepository(
            new MetadataSubscriberAccessorRepository([new ProfileProcessor($manager)]),
            $traceStack,
        );

        $repository = $manager->get(Profile::class);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            new ChainDoctrineSchemaConfigurator([
                $store,
                $subscriptionStore,
            ]),
        );

        $schemaDirector->create();

        $engine = new DefaultSubscriptionEngine(
            $store,
            $subscriptionStore,
            $subscriberAccessorRepository,
        );

        self::assertEquals(
            [
                new Subscription(
                    'profile',
                    'processor',
                    RunMode::FromNow,
                    lastSavedAt: new DateTimeImmutable('2021-01-01T00:00:00'),
                ),
            ],
            $engine->subscriptions(),
        );

        $engine->setup();
        $engine->boot();

        self::assertEquals(
            [
                new Subscription(
                    'profile',
                    'processor',
                    RunMode::FromNow,
                    Status::Active,
                    lastSavedAt: new DateTimeImmutable('2021-01-01T00:00:00'),
                ),
            ],
            $engine->subscriptions(),
        );

        $profile = Profile::create(ProfileId::fromString('1'), 'John');
        $repository->save($profile);

        $engine->run();

        $subscriptions = $engine->subscriptions();

        self::assertCount(1, $subscriptions);
        self::assertArrayHasKey(0, $subscriptions);

        $subscription = $subscriptions[0];

        self::assertEquals('profile', $subscription->id());

        self::assertEquals(Status::Active, $subscription->status());

        /** @var list<Message> $messages */
        $messages = iterator_to_array($store->load());

        self::assertCount(2, $messages);
        self::assertArrayHasKey(1, $messages);

        self::assertEquals(
            new TraceHeader([
                [
                    'name' => 'profile',
                    'category' => 'event_sourcing/subscriber/processor',
                ],
            ]),
            $messages[1]->header(TraceHeader::class),
        );
    }

    public function testBlueGreenDeployment(): void
    {
        // Test Setup

        $store = new DoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
        );

        $clock = new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00'));

        $subscriptionStore = new DoctrineSubscriptionStore(
            $this->connection,
            $clock,
        );

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => Profile::class]),
            $store,
        );

        $repository = $manager->get(Profile::class);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            new ChainDoctrineSchemaConfigurator([
                $store,
                $subscriptionStore,
            ]),
        );

        $schemaDirector->create();

        $firstEngine = new DefaultSubscriptionEngine(
            $store,
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([new ProfileProjection($this->projectionConnection)]),
        );

        // Deploy first version

        $firstEngine->setup();
        $firstEngine->boot();

        self::assertEquals(
            [
                new Subscription(
                    'profile_1',
                    'projector',
                    RunMode::FromBeginning,
                    Status::Active,
                    lastSavedAt: new DateTimeImmutable('2021-01-01T00:00:00'),
                ),
            ],
            $firstEngine->subscriptions(),
        );

        // Run first version

        $profile = Profile::create(ProfileId::fromString('1'), 'John');
        $repository->save($profile);

        $firstEngine->run();

        self::assertEquals(
            [
                new Subscription(
                    'profile_1',
                    'projector',
                    RunMode::FromBeginning,
                    Status::Active,
                    1,
                    lastSavedAt: new DateTimeImmutable('2021-01-01T00:00:00'),
                ),
            ],
            $firstEngine->subscriptions(),
        );

        // deploy second version

        $secondEngine = new DefaultSubscriptionEngine(
            $store,
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([new ProfileNewProjection($this->projectionConnection)]),
        );

        $secondEngine->setup();
        $secondEngine->boot();

        self::assertEquals(
            [
                new Subscription(
                    'profile_1',
                    'projector',
                    RunMode::FromBeginning,
                    Status::Active,
                    1,
                    lastSavedAt: new DateTimeImmutable('2021-01-01T00:00:00'),
                ),
                new Subscription(
                    'profile_2',
                    'projector',
                    RunMode::FromBeginning,
                    Status::Active,
                    1,
                    lastSavedAt: new DateTimeImmutable('2021-01-01T00:00:00'),
                ),
            ],
            $firstEngine->subscriptions(),
        );

        // switch traffic

        $secondEngine->run();

        self::assertEquals(
            [
                new Subscription(
                    'profile_1',
                    'projector',
                    RunMode::FromBeginning,
                    Status::Detached,
                    1,
                    lastSavedAt: new DateTimeImmutable('2021-01-01T00:00:00'),
                ),
                new Subscription(
                    'profile_2',
                    'projector',
                    RunMode::FromBeginning,
                    Status::Active,
                    1,
                    lastSavedAt: new DateTimeImmutable('2021-01-01T00:00:00'),
                ),
            ],
            $secondEngine->subscriptions(),
        );

        // shutdown first version

        $firstEngine->teardown();

        self::assertEquals(
            [
                new Subscription(
                    'profile_2',
                    'projector',
                    RunMode::FromBeginning,
                    Status::Active,
                    1,
                    lastSavedAt: new DateTimeImmutable('2021-01-01T00:00:00'),
                ),
            ],
            $secondEngine->subscriptions(),
        );
    }

    public function testBlueGreenDeploymentRollback(): void
    {
        // Test Setup

        $store = new DoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
        );

        $clock = new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00'));

        $subscriptionStore = new DoctrineSubscriptionStore(
            $this->connection,
            $clock,
        );

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => Profile::class]),
            $store,
        );

        $repository = $manager->get(Profile::class);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            new ChainDoctrineSchemaConfigurator([
                $store,
                $subscriptionStore,
            ]),
        );

        $schemaDirector->create();

        $firstEngine = new DefaultSubscriptionEngine(
            $store,
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([new ProfileProjection($this->projectionConnection)]),
        );

        // Deploy first version

        $firstEngine->setup();
        $firstEngine->boot();

        self::assertEquals(
            [
                new Subscription(
                    'profile_1',
                    'projector',
                    RunMode::FromBeginning,
                    Status::Active,
                    lastSavedAt: new DateTimeImmutable('2021-01-01T00:00:00'),
                ),
            ],
            $firstEngine->subscriptions(),
        );

        // Run first version

        $profile = Profile::create(ProfileId::fromString('1'), 'John');
        $repository->save($profile);

        $firstEngine->run();

        self::assertEquals(
            [
                new Subscription(
                    'profile_1',
                    'projector',
                    RunMode::FromBeginning,
                    Status::Active,
                    1,
                    lastSavedAt: new DateTimeImmutable('2021-01-01T00:00:00'),
                ),
            ],
            $firstEngine->subscriptions(),
        );

        // deploy second version

        $secondEngine = new DefaultSubscriptionEngine(
            $store,
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([new ProfileNewProjection($this->projectionConnection)]),
        );

        $secondEngine->setup();
        $secondEngine->boot();

        self::assertEquals(
            [
                new Subscription(
                    'profile_1',
                    'projector',
                    RunMode::FromBeginning,
                    Status::Active,
                    1,
                    lastSavedAt: new DateTimeImmutable('2021-01-01T00:00:00'),
                ),
                new Subscription(
                    'profile_2',
                    'projector',
                    RunMode::FromBeginning,
                    Status::Active,
                    1,
                    lastSavedAt: new DateTimeImmutable('2021-01-01T00:00:00'),
                ),
            ],
            $firstEngine->subscriptions(),
        );

        // switch traffic

        $secondEngine->run();

        self::assertEquals(
            [
                new Subscription(
                    'profile_1',
                    'projector',
                    RunMode::FromBeginning,
                    Status::Detached,
                    1,
                    lastSavedAt: new DateTimeImmutable('2021-01-01T00:00:00'),
                ),
                new Subscription(
                    'profile_2',
                    'projector',
                    RunMode::FromBeginning,
                    Status::Active,
                    1,
                    lastSavedAt: new DateTimeImmutable('2021-01-01T00:00:00'),
                ),
            ],
            $secondEngine->subscriptions(),
        );

        // rollback

        $firstEngine->setup();
        $firstEngine->boot();

        self::assertEquals(
            [
                new Subscription(
                    'profile_1',
                    'projector',
                    RunMode::FromBeginning,
                    Status::Detached,
                    1,
                    lastSavedAt: new DateTimeImmutable('2021-01-01T00:00:00'),
                ),
                new Subscription(
                    'profile_2',
                    'projector',
                    RunMode::FromBeginning,
                    Status::Active,
                    1,
                    lastSavedAt: new DateTimeImmutable('2021-01-01T00:00:00'),
                ),
            ],
            $firstEngine->subscriptions(),
        );

        // reactivating detached subscription

        $firstEngine->reactivate(new SubscriptionEngineCriteria(
            ids: ['profile_1'],
        ));

        // switch traffic

        $firstEngine->run();

        self::assertEquals(
            [
                new Subscription(
                    'profile_1',
                    'projector',
                    RunMode::FromBeginning,
                    Status::Active,
                    1,
                    lastSavedAt: new DateTimeImmutable('2021-01-01T00:00:00'),
                ),
                new Subscription(
                    'profile_2',
                    'projector',
                    RunMode::FromBeginning,
                    Status::Detached,
                    1,
                    lastSavedAt: new DateTimeImmutable('2021-01-01T00:00:00'),
                ),
            ],
            $firstEngine->subscriptions(),
        );

        // shutdown second version

        $secondEngine->teardown();

        self::assertEquals(
            [
                new Subscription(
                    'profile_1',
                    'projector',
                    RunMode::FromBeginning,
                    Status::Active,
                    1,
                    lastSavedAt: new DateTimeImmutable('2021-01-01T00:00:00'),
                ),
            ],
            $firstEngine->subscriptions(),
        );
    }

    /** @param list<Subscription> $subscriptions */
    private static function findSubscription(array $subscriptions, string $id): Subscription
    {
        foreach ($subscriptions as $subscription) {
            if ($subscription->id() === $id) {
                return $subscription;
            }
        }

        self::fail('subscription not found');
    }
}
