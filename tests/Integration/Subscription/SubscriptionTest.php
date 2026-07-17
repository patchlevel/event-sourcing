<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\Clock\FrozenClock;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventRegistryFactory;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Schema\ChainDoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\StreamCriterion;
use Patchlevel\EventSourcing\Store\StreamDoctrineDbalStore;
use Patchlevel\EventSourcing\Subscription\Cleanup\Dbal\DbalCleanupTaskHandler;
use Patchlevel\EventSourcing\Subscription\Cleanup\Dbal\DropTableTask;
use Patchlevel\EventSourcing\Subscription\Cleanup\DefaultCleaner;
use Patchlevel\EventSourcing\Subscription\Engine\CatchUpSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Boot;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Reactivate;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Refresh;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Remove;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Run;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Setup as SetupCommand;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Teardown as TeardownCommand;
use Patchlevel\EventSourcing\Subscription\Engine\DefaultSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnSubscriptionRemoved;
use Patchlevel\EventSourcing\Subscription\Engine\EventFilteredStoreMessageLoader;
use Patchlevel\EventSourcing\Subscription\Engine\GapResolverStoreMessageLoader;
use Patchlevel\EventSourcing\Subscription\Engine\Listener\RemoveSubscriptionStreamListener;
use Patchlevel\EventSourcing\Subscription\Engine\MessageLoader;
use Patchlevel\EventSourcing\Subscription\Engine\ProcessedResult;
use Patchlevel\EventSourcing\Subscription\Engine\Result;
use Patchlevel\EventSourcing\Subscription\Engine\StoreMessageLoader;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\ClockBasedRetryStrategy;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\RetryStrategyRepository;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\DoctrineSubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\EventEmitterResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\LookupResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Tests\DbalManager;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber\BatchProfileProjection;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber\ErrorProducerSubscriber;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber\ErrorProducerWithSelfRecoverySubscriber;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber\LookupSubscriber;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber\NotificationCollector;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber\NotificationEmittingProjection;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber\ProfileNewProjection;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber\ProfileProcessor;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber\ProfileProjection;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber\ProfileProjectionWithCleanup;
use Patchlevel\Hydrator\CoreExtension;
use Patchlevel\Hydrator\StackHydratorBuilder;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcher;

use function gc_collect_cycles;
use function iterator_to_array;

#[CoversNothing]
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
        $store = new StreamDoctrineDbalStore(
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

        $subscriberRepository = new MetadataSubscriberAccessorRepository([new ProfileProjection($this->projectionConnection)]);

        $engine = new DefaultSubscriptionEngine(
            new EventFilteredStoreMessageLoader($store, new AttributeEventMetadataFactory(), $subscriberRepository),
            $subscriptionStore,
            $subscriberRepository,
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

        $result = $engine->execute(new SetupCommand());

        self::assertEquals([], $result->errors);

        $result = $engine->execute(new Boot());

        self::assertProcessedMessages(0, $result);
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

        $profileId = ProfileId::generate();
        $profile = Profile::create($profileId, 'John');
        $repository->save($profile);

        $result = $engine->execute(new Run());

        self::assertProcessedMessages(1, $result);
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
            [$profileId->toString()],
        );

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertSame($profileId->toString(), $result['id']);
        self::assertSame('John', $result['name']);

        $result = $engine->execute(new Remove());
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

    public function testGapResolver(): void
    {
        $store = new StreamDoctrineDbalStore(
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

        $subscriberRepository = new MetadataSubscriberAccessorRepository([new ProfileProjection($this->projectionConnection)]);

        $engine = new DefaultSubscriptionEngine(
            new GapResolverStoreMessageLoader($store),
            $subscriptionStore,
            $subscriberRepository,
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

        $result = $engine->execute(new SetupCommand());

        self::assertEquals([], $result->errors);

        $result = $engine->execute(new Boot());

        self::assertProcessedMessages(0, $result);
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

        $profileId = ProfileId::generate();
        $profile = Profile::create($profileId, 'John');
        $repository->save($profile);

        $result = $engine->execute(new Run());

        self::assertProcessedMessages(1, $result);
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
            [$profileId->toString()],
        );

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertSame($profileId->toString(), $result['id']);
        self::assertSame('John', $result['name']);

        $result = $engine->execute(new Remove());
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

        $store = new StreamDoctrineDbalStore(
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
            new StoreMessageLoader($store),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            RetryStrategyRepository::withDefault(
                new ClockBasedRetryStrategy(
                    $clock,
                    ClockBasedRetryStrategy::DEFAULT_BASE_DELAY,
                    ClockBasedRetryStrategy::DEFAULT_DELAY_FACTOR,
                    2,
                ),
            ),
        );

        $result = $engine->execute(new SetupCommand());
        self::assertEquals([], $result->errors);

        $result = $engine->execute(new Boot());
        self::assertProcessedMessages(0, $result);
        self::assertEquals([], $result->errors);

        $subscription = self::findSubscription($engine->subscriptions(), 'error_producer');

        self::assertEquals(Status::Active, $subscription->status());
        self::assertEquals(null, $subscription->subscriptionError());
        self::assertEquals(0, $subscription->retryAttempt());

        $repository = $manager->get(Profile::class);

        $profile = Profile::create(ProfileId::generate(), 'John');
        $repository->save($profile);

        $subscriber->subscribeError = true;

        // first run, error

        $result = $engine->execute(new Run());

        self::assertProcessedMessages(1, $result);
        self::assertCount(1, $result->errors);

        $error = $result->errors[0];

        self::assertEquals('error_producer', $error->subscriptionId);
        self::assertEquals('subscribe error', $error->message);

        $subscription = self::findSubscription($engine->subscriptions(), 'error_producer');

        self::assertEquals(Status::Error, $subscription->status());
        self::assertEquals('subscribe error', $subscription->subscriptionError()?->errorMessage);
        self::assertEquals(Status::Active, $subscription->subscriptionError()?->previousStatus);
        self::assertEquals(0, $subscription->retryAttempt());

        // second run, time has not passed yet, no retry, no error

        $result = $engine->execute(new Run());

        self::assertProcessedMessages(0, $result);
        self::assertEquals([], $result->errors);

        $subscription = self::findSubscription($engine->subscriptions(), 'error_producer');

        self::assertEquals(Status::Error, $subscription->status());
        self::assertEquals('subscribe error', $subscription->subscriptionError()?->errorMessage);
        self::assertEquals(Status::Active, $subscription->subscriptionError()?->previousStatus);
        self::assertEquals(0, $subscription->retryAttempt());

        // third run, time has passed, 1. retry, error again

        $clock->sleep(5);
        $result = $engine->execute(new Run());

        self::assertProcessedMessages(1, $result);
        self::assertCount(1, $result->errors);

        $error = $result->errors[0];

        self::assertEquals('error_producer', $error->subscriptionId);
        self::assertEquals('subscribe error', $error->message);

        $subscription = self::findSubscription($engine->subscriptions(), 'error_producer');

        self::assertEquals(Status::Error, $subscription->status());
        self::assertEquals('subscribe error', $subscription->subscriptionError()?->errorMessage);
        self::assertEquals(Status::Active, $subscription->subscriptionError()?->previousStatus);
        self::assertEquals(1, $subscription->retryAttempt());

        // fourth run, time has passed, 2. retry, max retries reached, failed

        $clock->sleep(10);
        $result = $engine->execute(new Run());

        self::assertProcessedMessages(1, $result);
        self::assertCount(1, $result->errors);

        $error = $result->errors[0];

        self::assertEquals('error_producer', $error->subscriptionId);
        self::assertEquals('subscribe error', $error->message);

        $subscription = self::findSubscription($engine->subscriptions(), 'error_producer');

        self::assertEquals(Status::Failed, $subscription->status());
        self::assertEquals('subscribe error', $subscription->subscriptionError()?->errorMessage);
        self::assertEquals(Status::Active, $subscription->subscriptionError()?->previousStatus);
        self::assertEquals(2, $subscription->retryAttempt());

        // fifth run, time has passed, skip failed subscription

        $clock->sleep(20);
        $result = $engine->execute(new Run());

        self::assertProcessedMessages(0, $result);
        self::assertEquals([], $result->errors);

        $subscription = self::findSubscription($engine->subscriptions(), 'error_producer');

        self::assertEquals(Status::Failed, $subscription->status());
        self::assertEquals('subscribe error', $subscription->subscriptionError()?->errorMessage);
        self::assertEquals(Status::Active, $subscription->subscriptionError()?->previousStatus);
        self::assertEquals(2, $subscription->retryAttempt());

        // reactivated subscription

        $engine->execute(new Reactivate(
            ids: ['error_producer'],
        ));

        $subscription = self::findSubscription($engine->subscriptions(), 'error_producer');

        self::assertEquals(Status::Active, $subscription->status());
        self::assertEquals(null, $subscription->subscriptionError());
        self::assertEquals(0, $subscription->retryAttempt());

        // sixth run, error again

        $result = $engine->execute(new Run());

        self::assertProcessedMessages(1, $result);
        self::assertCount(1, $result->errors);

        $error = $result->errors[0];

        self::assertEquals('error_producer', $error->subscriptionId);
        self::assertEquals('subscribe error', $error->message);

        $subscription = self::findSubscription($engine->subscriptions(), 'error_producer');

        self::assertEquals(Status::Error, $subscription->status());
        self::assertEquals('subscribe error', $subscription->subscriptionError()?->errorMessage);
        self::assertEquals(Status::Active, $subscription->subscriptionError()?->previousStatus);
        self::assertEquals(0, $subscription->retryAttempt());

        // seventh run, time has passed, error fixed, 1. retry, no error

        $clock->sleep(5);
        $subscriber->subscribeError = false;

        $result = $engine->execute(new Run());

        self::assertProcessedMessages(1, $result);
        self::assertEquals([], $result->errors);

        $subscription = self::findSubscription($engine->subscriptions(), 'error_producer');

        self::assertEquals(Status::Active, $subscription->status());
        self::assertEquals(null, $subscription->subscriptionError());
        self::assertEquals(0, $subscription->retryAttempt());
    }

    public function testSelfRecovery(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00'));

        $store = new StreamDoctrineDbalStore(
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

        $subscriber = new ErrorProducerWithSelfRecoverySubscriber();

        $engine = new DefaultSubscriptionEngine(
            new StoreMessageLoader($store),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            RetryStrategyRepository::withDefault(
                new ClockBasedRetryStrategy(
                    $clock,
                    ClockBasedRetryStrategy::DEFAULT_BASE_DELAY,
                    ClockBasedRetryStrategy::DEFAULT_DELAY_FACTOR,
                    0,
                ),
            ),
        );

        $result = $engine->execute(new SetupCommand(skipBooting: true));
        self::assertEquals([], $result->errors);

        // add data

        $repository = $manager->get(Profile::class);

        $profile = Profile::create(ProfileId::generate(), 'John');
        $repository->save($profile);

        $subscriber->subscribeError = true;

        // first run, failed -> self recovery

        $result = $engine->execute(new Run());

        self::assertProcessedMessages(1, $result);
        self::assertCount(1, $result->errors);

        $error = $result->errors[0];

        self::assertEquals('error_producer', $error->subscriptionId);
        self::assertEquals('subscribe error', $error->message);

        $subscription = self::findSubscription($engine->subscriptions(), 'error_producer');

        self::assertEquals(Status::Active, $subscription->status());
        self::assertEquals(0, $subscription->retryAttempt());
        self::assertEquals(1, $subscription->position());

        // change data

        $profile->changeName('Jane');
        $repository->save($profile);

        // second run, failed -> self recovery failed

        $subscriber->onFailedError = true;
        $result = $engine->execute(new Run());

        self::assertProcessedMessages(1, $result);
        self::assertCount(1, $result->errors);

        $error = $result->errors[0];

        self::assertEquals('error_producer', $error->subscriptionId);
        self::assertEquals('subscribe error', $error->message);

        $subscription = self::findSubscription($engine->subscriptions(), 'error_producer');

        self::assertEquals(Status::Failed, $subscription->status());
        self::assertEquals(0, $subscription->retryAttempt());
        self::assertEquals(1, $subscription->position());
    }

    public function testLargeErrorMessage(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00'));

        $store = new StreamDoctrineDbalStore(
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

        $subscriber = new #[Subscriber('error_producer', RunMode::FromBeginning)]
        class {
            public bool $subscribeError = false;

            #[Setup]
            public function setup(): void
            {
            }

            #[Teardown]
            public function teardown(): void
            {
            }

            #[Subscribe('*')]
            public function subscribe(): void
            {
                if ($this->subscribeError) {
                    throw new RuntimeException('subscribe error: as an extra long message exceeding 255 varchar configuration, as an extra long message exceeding 255 varchar configuration, as an extra long message exceeding 255 varchar configuration, as an extra long message exceeding 255 varchar configuration, as an extra long message exceeding 255 varchar configuration, as an extra long message exceeding 255 varchar configuration, as an extra long message exceeding 255 varchar configuration, as an extra long message exceeding 255 varchar configuration, as an extra long message exceeding 255 varchar configuration.');
                }
            }
        };

        $engine = new DefaultSubscriptionEngine(
            new StoreMessageLoader($store),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            RetryStrategyRepository::withDefault(
                new ClockBasedRetryStrategy(
                    $clock,
                    ClockBasedRetryStrategy::DEFAULT_BASE_DELAY,
                    ClockBasedRetryStrategy::DEFAULT_DELAY_FACTOR,
                    2,
                ),
            ),
        );

        $result = $engine->execute(new SetupCommand());
        self::assertEquals([], $result->errors);

        $result = $engine->execute(new Boot());
        self::assertProcessedMessages(0, $result);
        self::assertEquals([], $result->errors);

        $subscription = self::findSubscription($engine->subscriptions(), 'error_producer');

        self::assertEquals(Status::Active, $subscription->status());
        self::assertEquals(null, $subscription->subscriptionError());
        self::assertEquals(0, $subscription->retryAttempt());

        $repository = $manager->get(Profile::class);

        $profile = Profile::create(ProfileId::generate(), 'John');
        $repository->save($profile);

        $subscriber->subscribeError = true;

        $result = $engine->execute(new Run());

        self::assertProcessedMessages(1, $result);
        self::assertCount(1, $result->errors);

        $error = $result->errors[0];

        self::assertEquals('error_producer', $error->subscriptionId);
        self::assertEquals(
            'subscribe error: as an extra long message exceeding 255 varchar configuration, as an extra long message exceeding 255 varchar configuration, as an extra long message exceeding 255 varchar configuration, as an extra long message exceeding 255 varchar configuration, as an extra long message exceeding 255 varchar configuration, as an extra long message exceeding 255 varchar configuration, as an extra long message exceeding 255 varchar configuration, as an extra long message exceeding 255 varchar configuration, as an extra long message exceeding 255 varchar configuration.',
            $error->message,
        );

        $subscription = self::findSubscription($engine->subscriptions(), 'error_producer');

        self::assertEquals(Status::Error, $subscription->status());
        self::assertEquals(
            'subscribe error: as an extra long message exceeding 255 varchar configuration, as an extra long message exceeding 255 varchar configuration, as an extra long message exceeding 255 varchar configuration, as an extra long message exceeding 255 varchar configuration, as an extra long message exceeding 255 varchar configuration, as an extra long message exceeding 255 varchar configuration, as an extra long message exceeding 255 varchar configuration, as an extra long message exceeding 255 varchar configuration, as an extra long message exceeding 255 varchar configuration.',
            $subscription->subscriptionError()?->errorMessage,
        );
        self::assertEquals(Status::Active, $subscription->subscriptionError()?->previousStatus);
        self::assertEquals(0, $subscription->retryAttempt());
    }

    public function testProcessor(): void
    {
        $store = new StreamDoctrineDbalStore(
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
            null,
            null,
        );

        $subscriberAccessorRepository = new MetadataSubscriberAccessorRepository([new ProfileProcessor($manager)]);

        $repository = $manager->get(Profile::class);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            new ChainDoctrineSchemaConfigurator([
                $store,
                $subscriptionStore,
            ]),
        );

        $schemaDirector->create();

        $engine = new CatchUpSubscriptionEngine(
            new DefaultSubscriptionEngine(
                new StoreMessageLoader($store),
                $subscriptionStore,
                $subscriberAccessorRepository,
            ),
        );

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

        $profile = Profile::create(ProfileId::generate(), 'John');
        $repository->save($profile);

        $engine->execute(new Run());

        $subscriptions = $engine->subscriptions();

        self::assertCount(1, $subscriptions);
        self::assertArrayHasKey(0, $subscriptions);

        $subscription = $subscriptions[0];

        self::assertEquals('profile', $subscription->id());

        self::assertEquals(Status::Active, $subscription->status());

        /** @var list<Message> $messages */
        $messages = iterator_to_array($store->load());

        self::assertCount(3, $messages);
        self::assertArrayHasKey(2, $messages);
    }

    public function testBlueGreenDeployment(): void
    {
        // Test Setup

        $store = new StreamDoctrineDbalStore(
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
            new StoreMessageLoader($store),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([new ProfileProjection($this->projectionConnection)]),
        );

        // Deploy first version

        $firstEngine->execute(new SetupCommand());
        $firstEngine->execute(new Boot());

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

        $profile = Profile::create(ProfileId::generate(), 'John');
        $repository->save($profile);

        $firstEngine->execute(new Run());

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
            new StoreMessageLoader($store),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([new ProfileNewProjection($this->projectionConnection)]),
        );

        $secondEngine->execute(new SetupCommand());
        $secondEngine->execute(new Boot());

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

        $secondEngine->execute(new Run());

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

        $firstEngine->execute(new TeardownCommand());

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

        $store = new StreamDoctrineDbalStore(
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
            new StoreMessageLoader($store),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([new ProfileProjection($this->projectionConnection)]),
        );

        // Deploy first version

        $firstEngine->execute(new SetupCommand());
        $firstEngine->execute(new Boot());

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

        $profile = Profile::create(ProfileId::generate(), 'John');
        $repository->save($profile);

        $firstEngine->execute(new Run());

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
            new StoreMessageLoader($store),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([new ProfileNewProjection($this->projectionConnection)]),
        );

        $secondEngine->execute(new SetupCommand());
        $secondEngine->execute(new Boot());

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

        $secondEngine->execute(new Run());

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

        $firstEngine->execute(new SetupCommand());
        $firstEngine->execute(new Boot());

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

        $firstEngine->execute(new Reactivate(
            ids: ['profile_1'],
        ));

        // switch traffic

        $firstEngine->execute(new Run());

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

        $secondEngine->execute(new TeardownCommand());

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

    public function testCleanup(): void
    {
        // Test Setup

        $cleaner = new DefaultCleaner([
            new DbalCleanupTaskHandler(
                $this->projectionConnection,
            ),
        ]);

        $store = new StreamDoctrineDbalStore(
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
            new StoreMessageLoader($store),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([new ProfileProjectionWithCleanup($this->projectionConnection)]),
            cleaner: $cleaner,
        );

        // Deploy first version

        $firstEngine->execute(new SetupCommand());
        $firstEngine->execute(new Boot());

        self::assertEquals(
            [
                new Subscription(
                    'profile_1',
                    'projector',
                    RunMode::FromBeginning,
                    Status::Active,
                    lastSavedAt: new DateTimeImmutable('2021-01-01T00:00:00'),
                    cleanupTasks: [new DropTableTask('projection_profile_1')],
                ),
            ],
            $firstEngine->subscriptions(),
        );

        // Run first version

        $profile = Profile::create(ProfileId::generate(), 'John');
        $repository->save($profile);

        $firstEngine->execute(new Run());

        self::assertEquals(
            [
                new Subscription(
                    'profile_1',
                    'projector',
                    RunMode::FromBeginning,
                    Status::Active,
                    1,
                    lastSavedAt: new DateTimeImmutable('2021-01-01T00:00:00'),
                    cleanupTasks: [new DropTableTask('projection_profile_1')],
                ),
            ],
            $firstEngine->subscriptions(),
        );

        // deploy second version

        $secondEngine = new DefaultSubscriptionEngine(
            new StoreMessageLoader($store),
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository([new ProfileNewProjection($this->projectionConnection)]),
            cleaner: $cleaner,
        );

        $secondEngine->execute(new SetupCommand());
        $secondEngine->execute(new Boot());

        self::assertEquals(
            [
                new Subscription(
                    'profile_1',
                    'projector',
                    RunMode::FromBeginning,
                    Status::Active,
                    1,
                    lastSavedAt: new DateTimeImmutable('2021-01-01T00:00:00'),
                    cleanupTasks: [new DropTableTask('projection_profile_1')],
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

        $secondEngine->execute(new Run());

        self::assertEquals(
            [
                new Subscription(
                    'profile_1',
                    'projector',
                    RunMode::FromBeginning,
                    Status::Detached,
                    1,
                    lastSavedAt: new DateTimeImmutable('2021-01-01T00:00:00'),
                    cleanupTasks: [new DropTableTask('projection_profile_1')],
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

        // shutdown second version (with cleanup)

        $secondEngine->execute(new TeardownCommand());

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

        self::assertFalse(
            $this->projectionConnection->createSchemaManager()->tableExists('projection_profile_1'),
        );
    }

    public function testLookup(): void
    {
        $eventRegistry = (new AttributeEventRegistryFactory())->create([__DIR__ . '/Events']);
        $serializer = new DefaultEventSerializer(
            $eventRegistry,
            (new StackHydratorBuilder())->useExtension(new CoreExtension())->build(),
        );

        $store = new StreamDoctrineDbalStore(
            $this->connection,
            $serializer,
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

        $subscriberRepository = new MetadataSubscriberAccessorRepository(
            [
                new LookupSubscriber($this->projectionConnection),
            ],
        );

        $engine = new DefaultSubscriptionEngine(
            new StoreMessageLoader($store),
            $subscriptionStore,
            $subscriberRepository,
            argumentResolvers: [
                new LookupResolver(
                    $store,
                    $eventRegistry,
                ),
            ],
        );

        $result = $engine->execute(new SetupCommand());

        self::assertEquals([], $result->errors);

        $result = $engine->execute(new Boot());

        self::assertProcessedMessages(0, $result);
        self::assertEquals([], $result->errors);

        $profileId = ProfileId::generate();
        $profile = Profile::create($profileId, 'John');
        $repository->save($profile);

        $result = $engine->execute(new Run());

        self::assertProcessedMessages(1, $result);
        self::assertEquals([], $result->errors);

        $result = $this->projectionConnection->fetchAssociative(
            'SELECT * FROM projection_lookup WHERE id = ?',
            [$profileId->toString()],
        );

        self::assertFalse($result);

        $profile->changeName('Hans');
        $profile->promoteToAdmin();
        $repository->save($profile);

        $result = $engine->execute(new Run());

        self::assertProcessedMessages(2, $result);
        self::assertEquals([], $result->errors);

        $result = $this->projectionConnection->fetchAssociative(
            'SELECT * FROM projection_lookup WHERE id = ?',
            [$profileId->toString()],
        );

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertSame($profileId->toString(), $result['id']);
        self::assertSame('Hans', $result['name']);
    }

    public function testEventEmitter(): void
    {
        $store = new StreamDoctrineDbalStore(
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

        $collector = new NotificationCollector();

        $subscriberRepository = new MetadataSubscriberAccessorRepository(
            [
                new NotificationEmittingProjection(),
                $collector,
            ],
        );

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            OnSubscriptionRemoved::class,
            new RemoveSubscriptionStreamListener($store),
        );

        $engine = new DefaultSubscriptionEngine(
            new StoreMessageLoader($store),
            $subscriptionStore,
            $subscriberRepository,
            eventDispatcher: $eventDispatcher,
            argumentResolvers: [
                new EventEmitterResolver($store),
            ],
        );

        $engine->execute(new SetupCommand());
        $engine->execute(new Boot());

        $profileId = ProfileId::generate();
        $repository->save(Profile::create($profileId, 'John'));

        // the emitting projection reacts to ProfileCreated and emits a NotificationSent event
        // into its projection stream, which the notification subscriber then consumes. Depending
        // on the database driver this can happen in one or two runs, so we drain the engine.
        do {
            $result = $engine->execute(new Run());

            self::assertInstanceOf(ProcessedResult::class, $result);
            self::assertEquals([], $result->errors);
        } while ($result->processedMessages > 0);

        self::assertSame(1, $store->count(new Criteria(new StreamCriterion('subscription_emitting'))));
        self::assertCount(1, $collector->notifications);
        self::assertEquals($profileId, $collector->notifications[0]->profileId);

        // removing the subscriptions also removes the projection stream
        $engine->execute(new Remove());

        self::assertNotContains('subscription_emitting', $store->streams());
        self::assertSame(0, $store->count(new Criteria(new StreamCriterion('subscription_emitting'))));
    }

    public function testRefreshSubscriptions(): void
    {
        $store = new StreamDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
        );

        $clock = new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00'));

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

        $subscriber = new #[Subscriber('test', RunMode::FromBeginning, group: 'default')]
        class {
        };

        $subscriberRepository = new MetadataSubscriberAccessorRepository([$subscriber]);

        $engine = new DefaultSubscriptionEngine(
            $this->createMock(MessageLoader::class),
            $subscriptionStore,
            $subscriberRepository,
        );

        $engine->execute(new SetupCommand());

        $subscriptions = $engine->subscriptions();
        self::assertCount(1, $subscriptions);
        self::assertEquals('test', $subscriptions[0]->id());
        self::assertEquals('default', $subscriptions[0]->group());
        self::assertEquals(RunMode::FromBeginning, $subscriptions[0]->runMode());

        // change subscriber metadata
        $newSubscriber = new #[Subscriber('test', RunMode::FromNow, group: 'new-group')]
        class {
        };

        $newSubscriberRepository = new MetadataSubscriberAccessorRepository([$newSubscriber]);

        $engine = new DefaultSubscriptionEngine(
            $this->createMock(MessageLoader::class),
            $subscriptionStore,
            $newSubscriberRepository,
        );

        $engine->execute(new Refresh());

        $subscriptions = $engine->subscriptions();
        self::assertCount(1, $subscriptions);
        self::assertEquals('test', $subscriptions[0]->id());
        self::assertEquals('new-group', $subscriptions[0]->group());
        self::assertEquals(RunMode::FromNow, $subscriptions[0]->runMode());
    }

    public function testBatch(): void
    {
        $store = new StreamDoctrineDbalStore(
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

        $projection = new BatchProfileProjection($this->projectionConnection);
        $subscriberRepository = new MetadataSubscriberAccessorRepository([$projection]);

        $engine = new DefaultSubscriptionEngine(
            new EventFilteredStoreMessageLoader($store, new AttributeEventMetadataFactory(), $subscriberRepository),
            $subscriptionStore,
            $subscriberRepository,
        );

        $result = $engine->execute(new SetupCommand());
        self::assertEquals([], $result->errors);

        $result = $engine->execute(new Boot());
        self::assertProcessedMessages(0, $result);
        self::assertEquals([], $result->errors);

        $aliceId = ProfileId::generate();
        $bobId = ProfileId::generate();
        $charlieId = ProfileId::generate();

        $repository->save(Profile::create($aliceId, 'Alice'));
        $repository->save(Profile::create($bobId, 'Bob'));
        $repository->save(Profile::create($charlieId, 'Charlie'));

        $result = $engine->execute(new Run());

        self::assertProcessedMessages(3, $result);
        self::assertEquals([], $result->errors);

        // all three events were processed in a single batch: one begin, one flush, no rollback
        self::assertSame(1, $projection->beginCount);
        self::assertSame(1, $projection->flushCount);
        self::assertSame(0, $projection->rollbackCount);

        self::assertEquals(
            [
                new Subscription(
                    'batch_profile',
                    'projector',
                    RunMode::FromBeginning,
                    Status::Active,
                    3,
                    lastSavedAt: new DateTimeImmutable('2021-01-01T00:00:00'),
                ),
            ],
            $engine->subscriptions(),
        );

        $aliceRow = $this->projectionConnection->fetchAssociative(
            'SELECT * FROM projection_batch_profile WHERE id = ?',
            [$aliceId->toString()],
        );

        self::assertIsArray($aliceRow);
        self::assertSame('Alice', $aliceRow['name']);

        $bobRow = $this->projectionConnection->fetchAssociative(
            'SELECT * FROM projection_batch_profile WHERE id = ?',
            [$bobId->toString()],
        );

        self::assertIsArray($bobRow);
        self::assertSame('Bob', $bobRow['name']);

        $charlieRow = $this->projectionConnection->fetchAssociative(
            'SELECT * FROM projection_batch_profile WHERE id = ?',
            [$charlieId->toString()],
        );

        self::assertIsArray($charlieRow);
        self::assertSame('Charlie', $charlieRow['name']);
    }

    public function testBatchRollback(): void
    {
        $store = new StreamDoctrineDbalStore(
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

        $projection = new BatchProfileProjection($this->projectionConnection);
        $subscriberRepository = new MetadataSubscriberAccessorRepository([$projection]);

        $engine = new DefaultSubscriptionEngine(
            new EventFilteredStoreMessageLoader($store, new AttributeEventMetadataFactory(), $subscriberRepository),
            $subscriptionStore,
            $subscriberRepository,
        );

        $engine->execute(new SetupCommand());
        $engine->execute(new Boot());

        // first batch commits successfully
        $aliceId = ProfileId::generate();
        $repository->save(Profile::create($aliceId, 'Alice'));

        $result = $engine->execute(new Run());

        self::assertProcessedMessages(1, $result);
        self::assertEquals([], $result->errors);
        self::assertSame(1, $projection->flushCount);

        // second batch inserts Bob and then hits a poisoned event, which rolls the batch back
        $bobId = ProfileId::generate();
        $bob = Profile::create($bobId, 'Bob');
        $bob->changeName(BatchProfileProjection::POISON);
        $repository->save($bob);

        $result = $engine->execute(new Run());

        self::assertCount(1, $result->errors);
        self::assertSame(2, $projection->beginCount);
        self::assertSame(1, $projection->flushCount);
        self::assertSame(1, $projection->rollbackCount);

        $subscription = self::findSubscription($engine->subscriptions(), 'batch_profile');

        // the position stays at the last successfully committed event
        self::assertEquals(Status::Error, $subscription->status());
        self::assertEquals(1, $subscription->position());

        // Alice (committed in the first batch) survives, Bob's insert was rolled back
        $aliceRow = $this->projectionConnection->fetchAssociative(
            'SELECT * FROM projection_batch_profile WHERE id = ?',
            [$aliceId->toString()],
        );

        self::assertIsArray($aliceRow);
        self::assertSame('Alice', $aliceRow['name']);

        $bobRow = $this->projectionConnection->fetchAssociative(
            'SELECT * FROM projection_batch_profile WHERE id = ?',
            [$bobId->toString()],
        );

        self::assertFalse($bobRow);
    }

    public function testSkipLockedClaim(): void
    {
        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            self::markTestSkipped('SQLite serializes writes and does not support SKIP LOCKED.');
        }

        $storeWorkerA = new DoctrineSubscriptionStore($this->connection);
        $storeWorkerB = new DoctrineSubscriptionStore($this->projectionConnection);

        $schemaDirector = new DoctrineSchemaDirector($this->connection, $storeWorkerA);
        $schemaDirector->create();

        $storeWorkerA->add(new Subscription('a', 'default', RunMode::FromBeginning, Status::Active));
        $storeWorkerA->add(new Subscription('b', 'default', RunMode::FromBeginning, Status::Active));

        $criteria = new SubscriptionCriteria(status: [Status::Active]);

        $this->connection->beginTransaction();
        $claimedByA = $storeWorkerA->claim('a', $criteria);

        self::assertNotNull($claimedByA);
        self::assertSame('a', $claimedByA->id());

        $lockedClaim = $storeWorkerB->claim('a', $criteria);
        self::assertNull($lockedClaim);

        $claimedByB = $storeWorkerB->claim('b', $criteria);
        self::assertNotNull($claimedByB);
        self::assertSame('b', $claimedByB->id());

        $this->connection->commit();

        $reclaimed = $storeWorkerB->claim('a', $criteria);
        self::assertNotNull($reclaimed);
        self::assertSame('a', $reclaimed->id());
    }

    /** @phpstan-assert ProcessedResult $result */
    private static function assertProcessedMessages(int $expected, Result $result): void
    {
        self::assertInstanceOf(ProcessedResult::class, $result);
        self::assertSame($expected, $result->processedMessages);
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
