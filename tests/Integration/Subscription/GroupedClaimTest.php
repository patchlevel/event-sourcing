<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Table;
use Patchlevel\EventSourcing\Attribute\BatchBegin;
use Patchlevel\EventSourcing\Attribute\BatchFlush;
use Patchlevel\EventSourcing\Attribute\BatchRollback;
use Patchlevel\EventSourcing\Attribute\BatchState;
use Patchlevel\EventSourcing\Attribute\Projector;
use Patchlevel\EventSourcing\Attribute\Setup;
use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\Clock\FrozenClock;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Schema\ChainDoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\StreamDoctrineDbalStore;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Run;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Setup as SetupCommand;
use Patchlevel\EventSourcing\Subscription\Engine\DefaultSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\EventFilteredStoreMessageLoader;
use Patchlevel\EventSourcing\Subscription\Engine\ProcessedResult;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\ClockBasedRetryStrategy;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\RetryStrategyRepository;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\DoctrineSubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Tests\DbalManager;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\NameChanged;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber\BatchProfileProjection;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

use function gc_collect_cycles;

#[CoversNothing]
final class GroupedClaimTest extends TestCase
{
    private Connection $connection;

    public function setUp(): void
    {
        $this->connection = DbalManager::createConnection();
    }

    public function tearDown(): void
    {
        $this->connection->close();

        gc_collect_cycles();
    }

    public function testSubscriptionsOfAGroupAreProcessedTogetherAndFailIndependently(): void
    {
        $store = new StreamDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
        );

        $clock = new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00'));
        $subscriptionStore = new DoctrineSubscriptionStore($this->connection, $clock);

        (new DoctrineSchemaDirector(
            $this->connection,
            new ChainDoctrineSchemaConfigurator([$store, $subscriptionStore]),
        ))->create();

        $first = new #[Projector('first')]
        class {
            /** @var list<string> */
            public array $names = [];

            #[Subscribe(ProfileCreated::class)]
            public function onProfileCreated(ProfileCreated $event): void
            {
                $this->names[] = $event->name;
            }
        };

        $failing = new #[Projector('failing')]
        class {
            #[Subscribe(ProfileCreated::class)]
            public function onProfileCreated(ProfileCreated $event): void
            {
                throw new RuntimeException('boom');
            }
        };

        $second = new #[Projector('second')]
        class {
            /** @var list<string> */
            public array $names = [];

            #[Subscribe(ProfileCreated::class)]
            public function onProfileCreated(ProfileCreated $event): void
            {
                $this->names[] = $event->name;
            }
        };

        $subscriberRepository = new MetadataSubscriberAccessorRepository([$first, $failing, $second]);

        $engine = new DefaultSubscriptionEngine(
            new EventFilteredStoreMessageLoader($store, new AttributeEventMetadataFactory(), $subscriberRepository),
            $subscriptionStore,
            $subscriberRepository,
            new RetryStrategyRepository([
                RetryStrategyRepository::DEFAULT_STRATEGY_NAME => new ClockBasedRetryStrategy($clock),
            ]),
        );

        $repository = (new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => Profile::class]),
            $store,
        ))->get(Profile::class);

        $engine->execute(new SetupCommand(skipBooting: true));

        $repository->save(Profile::create(ProfileId::generate(), 'John'));
        $repository->save(Profile::create(ProfileId::generate(), 'Jane'));

        $result = $engine->execute(new Run(subscriptionsPerClaim: 10));

        self::assertInstanceOf(ProcessedResult::class, $result);
        self::assertSame(2, $result->processedMessages, 'the group reads the stream once');
        self::assertCount(1, $result->errors);

        self::assertSame(['John', 'Jane'], $first->names);
        self::assertSame(['John', 'Jane'], $second->names);

        self::assertSame(Status::Active, $subscriptionStore->get('first')->status());
        self::assertSame(Status::Error, $subscriptionStore->get('failing')->status());
        self::assertSame(Status::Active, $subscriptionStore->get('second')->status());
        self::assertSame(2, $subscriptionStore->get('second')->position());
    }

    public function testTransactionalBatchesOfAGroupDoNotOverlap(): void
    {
        $store = new StreamDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
        );

        $clock = new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00'));
        $subscriptionStore = new DoctrineSubscriptionStore($this->connection, $clock);

        (new DoctrineSchemaDirector(
            $this->connection,
            new ChainDoctrineSchemaConfigurator([$store, $subscriptionStore]),
        ))->create();

        // both keep a transaction open from batch begin to flush on the same connection
        $poisoned = new BatchProfileProjection($this->connection);
        $healthy = new #[Projector('healthy_batch')]
        class ($this->connection) {
            public function __construct(
                private readonly Connection $connection,
            ) {
            }

            #[Setup]
            public function create(): void
            {
                $table = new Table('healthy_batch');
                $table->addColumn('id', 'string')->setLength(36);
                $table->addColumn('name', 'string')->setLength(255);
                $table->setPrimaryKey(['id']);

                $this->connection->createSchemaManager()->createTable($table);
            }

            #[BatchBegin]
            public function begin(): stdClass
            {
                $this->connection->beginTransaction();

                return new stdClass();
            }

            #[Subscribe(ProfileCreated::class)]
            public function onProfileCreated(ProfileCreated $event, #[BatchState]
            stdClass $state,): void
            {
                $this->connection->insert('healthy_batch', ['id' => $event->profileId->toString(), 'name' => $event->name]);
            }

            #[Subscribe(NameChanged::class)]
            public function onNameChanged(NameChanged $event, #[BatchState]
            stdClass $state,): void
            {
                $this->connection->update('healthy_batch', ['name' => $event->name], ['id' => $event->profileId->toString()]);
            }

            #[BatchFlush]
            public function flush(stdClass $state): void
            {
                $this->connection->commit();
            }

            #[BatchRollback]
            public function rollback(stdClass $state): void
            {
                $this->connection->rollBack();
            }
        };

        $subscriberRepository = new MetadataSubscriberAccessorRepository([$poisoned, $healthy]);

        $engine = new DefaultSubscriptionEngine(
            new EventFilteredStoreMessageLoader($store, new AttributeEventMetadataFactory(), $subscriberRepository),
            $subscriptionStore,
            $subscriberRepository,
            new RetryStrategyRepository([
                RetryStrategyRepository::DEFAULT_STRATEGY_NAME => new ClockBasedRetryStrategy($clock),
            ]),
        );

        $repository = (new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => Profile::class]),
            $store,
        ))->get(Profile::class);

        $engine->execute(new SetupCommand(skipBooting: true));

        $id = ProfileId::generate();
        $repository->save(Profile::create($id, 'John'));
        $profile = $repository->load($id);
        $profile->changeName(BatchProfileProjection::POISON);
        $repository->save($profile);

        // without separate batches, the rollback of the poisoned projection would hit the transaction of the healthy one
        for ($i = 0; $i < 5; $i++) {
            $engine->execute(new Run(subscriptionsPerClaim: 10));
        }

        self::assertSame([], $this->connection->fetchAllAssociative('SELECT * FROM projection_' . BatchProfileProjection::SUBSCRIBER_ID));
        self::assertSame(Status::Error, $subscriptionStore->get(BatchProfileProjection::SUBSCRIBER_ID)->status());
        self::assertSame(
            [['id' => $id->toString(), 'name' => BatchProfileProjection::POISON]],
            $this->connection->fetchAllAssociative('SELECT id, name FROM healthy_batch'),
        );
        self::assertSame(Status::Active, $subscriptionStore->get('healthy_batch')->status());
    }
}
