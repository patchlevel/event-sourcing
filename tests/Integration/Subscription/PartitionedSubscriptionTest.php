<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Clock\FrozenClock;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Schema\ChainDoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\StreamCriterion;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\StreamDoctrineDbalStore;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Boot;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Remove;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Run;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Setup;
use Patchlevel\EventSourcing\Subscription\Engine\DefaultSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\EventFilteredStoreMessageLoader;
use Patchlevel\EventSourcing\Subscription\Engine\ProcessedResult;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Repository\RunSubscriptionEngineRepository;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\ClockBasedRetryStrategy;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\RetryStrategyRepository;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\DoctrineSubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Tests\DbalManager;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Events\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber\EphemeralPartitionProjection;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber\OncePartitionedProjection;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber\PartitionedBatchProfileProjection;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber\PartitionedProfileProjection;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber\PartitionedTransactionalBatchProjection;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_keys;
use function array_map;
use function array_values;
use function gc_collect_cycles;
use function random_int;
use function sort;

#[CoversNothing]
final class PartitionedSubscriptionTest extends TestCase
{
    private const MAIN = PartitionedProfileProjection::SUBSCRIBER_ID;

    private Connection $connection;
    private FrozenClock $clock;
    private DoctrineSubscriptionStore $subscriptionStore;
    private StreamDoctrineDbalStore $store;

    /** @var Repository<Profile> */
    private Repository $repository;

    public function setUp(): void
    {
        $this->connection = DbalManager::createConnection();
        $this->clock = new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00'));
    }

    public function tearDown(): void
    {
        $this->connection->close();

        gc_collect_cycles();
    }

    public function testRoutesEveryMessageIntoThePartitionOfItsKey(): void
    {
        $projection = new PartitionedProfileProjection();
        $engine = $this->engine($projection);

        $engine->execute(new Setup(skipBooting: true));

        $ids = $this->createProfiles(3);

        $result = $engine->execute(new Run());
        self::assertInstanceOf(ProcessedResult::class, $result);
        self::assertTrue($result->finished, 'the partitions process the routed messages in the same run');

        $partitions = $this->partitions();
        self::assertCount(3, $partitions);

        foreach ($ids as $i => $id) {
            $partition = $partitions[Subscription::partitionId(self::MAIN, 'profile-' . $id)];

            self::assertSame(self::MAIN, $partition->subscriberId());
            self::assertSame('profile-' . $id, $partition->partitionKey());
            self::assertSame(Status::Active, $partition->status());
            self::assertSame(['name-' . $i], $projection->processed[$id]);
            self::assertSame('profile-' . $id, $projection->partitionKeys[$id], 'the handler gets the key of its partition');
        }

        self::assertSame(3, $projection->handled, 'only the partitions call the subscriber, every message once');

        foreach ($this->partitions() as $partition) {
            self::assertSame([], $this->subscriptionStore->inbox([$partition->id() => -1], 10), 'processed messages leave the inbox');
        }
    }

    public function testFailureOnlyStopsTheAffectedPartition(): void
    {
        $projection = new PartitionedProfileProjection();
        $projection->failingNames = ['broken'];
        $engine = $this->engine($projection);

        $engine->execute(new Setup(skipBooting: true));

        [$brokenId, $healthyId] = $this->createProfiles(2, ['broken', 'healthy']);

        $this->runUntilFinished($engine);

        self::assertSame(['healthy'], $projection->processed[$healthyId]);
        self::assertArrayNotHasKey($brokenId, $projection->processed);

        $partitions = $this->partitions();
        self::assertSame(Status::Error, $partitions[Subscription::partitionId(self::MAIN, 'profile-' . $brokenId)]->status());
        self::assertSame(Status::Active, $partitions[Subscription::partitionId(self::MAIN, 'profile-' . $healthyId)]->status());
        self::assertSame(Status::Active, $this->subscriptionStore->get(self::MAIN)->status());

        $projection->failingNames = [];
        $this->clock->sleep(10);

        $this->runUntilFinished($engine);

        self::assertSame(['broken'], $projection->processed[$brokenId]);
    }

    /**
     * The key is resolved once by the main subscription. A partition that is behind (here because of an error) still
     * processes the messages routed to it, even if the key method returns something else by now.
     */
    public function testChangedPartitionKeyDoesNotLoseMessages(): void
    {
        $projection = new PartitionedProfileProjection();
        $engine = $this->engine($projection);
        $projection->partitionKey = static fn (Message $message): string => 'v1-' . $message->header(StreamNameHeader::class)->streamName;

        $engine->execute(new Setup(skipBooting: true));

        $profileId = ProfileId::generate();
        $this->repository->save(Profile::create($profileId, 'n1'));
        $this->runUntilFinished($engine);

        $projection->failingNames = ['n2'];
        $this->changeName($profileId, 'n2');
        $this->runUntilFinished($engine);

        // deploy: the key method changes and the bug is fixed while the partition is still in error
        $projection->partitionKey = static fn (Message $message): string => 'v2-' . $message->header(StreamNameHeader::class)->streamName;
        $projection->failingNames = [];
        $this->changeName($profileId, 'n3');

        $this->clock->sleep(10);
        $this->runUntilFinished($engine);

        $names = $projection->processed[$profileId->toString()];
        sort($names);

        self::assertSame(['n1', 'n2', 'n3'], $names);
        self::assertCount(2, $this->partitions());

        foreach ($this->partitions() as $partition) {
            self::assertSame(Status::Active, $partition->status(), $partition->id());
        }
    }

    public function testNonDeterministicPartitionKeyProcessesEveryMessageExactlyOnce(): void
    {
        $projection = new PartitionedProfileProjection();
        $projection->partitionKey = static fn (Message $message): string => 'bucket-' . random_int(1, 5);
        $engine = $this->engine($projection);

        $engine->execute(new Setup(skipBooting: true));

        $ids = $this->createProfiles(10);

        foreach ($ids as $id) {
            $this->changeName(ProfileId::fromString($id), 'changed-' . $id);
        }

        $this->runUntilFinished($engine);

        foreach ($ids as $i => $id) {
            $names = $projection->processed[$id];
            sort($names);

            self::assertSame(['changed-' . $id, 'name-' . $i], $names);
        }
    }

    public function testIdlePartitionsAreNotClaimed(): void
    {
        $projection = new PartitionedProfileProjection();
        $engine = $this->engine($projection);

        $engine->execute(new Setup(skipBooting: true));

        [$activeId, $idleId] = $this->createProfiles(2);
        $this->runUntilFinished($engine);

        $idlePartitionId = Subscription::partitionId(self::MAIN, 'profile-' . $idleId);
        $lastSavedAt = $this->subscriptionStore->get($idlePartitionId)->lastSavedAt();

        $this->clock->sleep(60);
        $this->changeName(ProfileId::fromString($activeId), 'changed');
        $this->runUntilFinished($engine);

        self::assertSame(['name-0', 'changed'], $projection->processed[$activeId]);
        self::assertEquals(
            $lastSavedAt,
            $this->subscriptionStore->get($idlePartitionId)->lastSavedAt(),
            'a partition without messages in its inbox is not claimed',
        );
    }

    public function testRunWithLimitProcessesTheInboxInChunks(): void
    {
        $projection = new PartitionedProfileProjection();
        $projection->partitionKey = static fn (Message $message): string => 'all';
        $engine = $this->engine($projection);

        $engine->execute(new Setup(skipBooting: true));

        $ids = $this->createProfiles(5);

        $this->runUntilFinished($engine, 2);

        self::assertSame($ids, array_keys($projection->processed), 'one partition keeps the order of the messages');
    }

    public function testBootProcessesThePartitions(): void
    {
        $projection = new PartitionedProfileProjection();
        $engine = $this->engine($projection);

        $ids = $this->createProfiles(3);

        $engine->execute(new Setup());

        for ($i = 0; $i < 20; $i++) {
            $result = $engine->execute(new Boot(limit: 1));
            self::assertInstanceOf(ProcessedResult::class, $result);

            if ($result->finished) {
                break;
            }
        }

        self::assertSame(Status::Active, $this->subscriptionStore->get(self::MAIN)->status());

        foreach ($this->partitions() as $partition) {
            self::assertSame(Status::Active, $partition->status(), $partition->id());
        }

        foreach ($ids as $i => $id) {
            self::assertSame(['name-' . $i], $projection->processed[$id]);
        }
    }

    public function testRemoveAlsoRemovesThePartitions(): void
    {
        $projection = new PartitionedProfileProjection();
        $engine = $this->engine($projection);

        $engine->execute(new Setup(skipBooting: true));
        $this->createProfiles(2);
        $engine->execute(new Run());

        self::assertCount(2, $this->partitions());

        $engine->execute(new Remove());

        self::assertSame([], $this->subscriptionStore->find(new SubscriptionCriteria(ids: [self::MAIN])));

        self::assertSame([], $this->connection->fetchFirstColumn('SELECT subscription_id FROM subscriptions_inbox'));
    }

    public function testBatchSubscriberWithPartitions(): void
    {
        $projection = new PartitionedBatchProfileProjection();
        $engine = $this->engine($projection);

        $engine->execute(new Setup(skipBooting: true));

        $ids = $this->createProfiles(3);

        foreach ($ids as $id) {
            $this->changeName(ProfileId::fromString($id), 'changed-' . $id);
            $this->changeName(ProfileId::fromString($id), 'again-' . $id);
        }

        $this->runUntilFinished($engine);

        foreach ($ids as $i => $id) {
            self::assertSame(['name-' . $i, 'changed-' . $id, 'again-' . $id], $projection->flushed[$id]);
        }

        foreach ($this->partitions(PartitionedBatchProfileProjection::SUBSCRIBER_ID) as $partition) {
            self::assertSame([], $this->subscriptionStore->inbox([$partition->id() => -1], 10), $partition->id());
        }
    }

    /** A routed message whose stream was removed before the partition processed it must not block the partition. */
    public function testRemovedMessagesDoNotBlockThePartition(): void
    {
        $projection = new PartitionedProfileProjection();
        $engine = $this->engine($projection);

        $engine->execute(new Setup(skipBooting: true));

        [$removedId, $keptId] = $this->createProfiles(2);
        $this->runUntilFinished($engine);

        // the partition fails, so the message is still in its inbox when its stream is removed
        $projection->failingNames = ['removed'];
        $this->changeName(ProfileId::fromString($removedId), 'removed');
        $this->changeName(ProfileId::fromString($keptId), 'kept');
        $this->runUntilFinished($engine);

        $this->store->remove(new Criteria(new StreamCriterion('profile-' . $removedId)));

        $projection->failingNames = [];
        $this->clock->sleep(10);
        $this->runUntilFinished($engine);

        self::assertSame(['name-1', 'kept'], $projection->processed[$keptId]);
        self::assertSame(['name-0'], $projection->processed[$removedId]);

        $partitionId = Subscription::partitionId(self::MAIN, 'profile-' . $removedId);

        self::assertSame(Status::Active, $this->subscriptionStore->get($partitionId)->status());
        self::assertSame([], $this->subscriptionStore->inbox([$partitionId => -1], 10), 'the removed message left the inbox');
        self::assertSame(
            [],
            $this->subscriptionStore->find(new SubscriptionCriteria(status: [Status::Active], pendingPartitionsOnly: true, partitions: true)),
            'no partition waits for a message that does not exist anymore',
        );
    }

    /** More new keys than one lookup query covers, then messages for the existing partitions. */
    public function testManyPartitionsAreLookedUpInChunks(): void
    {
        $projection = new PartitionedProfileProjection();
        $engine = $this->engine($projection);

        $engine->execute(new Setup(skipBooting: true));

        $ids = $this->createProfiles(600);
        $this->runUntilFinished($engine);

        self::assertCount(600, $this->partitions());

        foreach ($ids as $id) {
            $this->changeName(ProfileId::fromString($id), 'changed-' . $id);
        }

        $this->runUntilFinished($engine);

        self::assertCount(600, $this->partitions());

        foreach ($ids as $i => $id) {
            self::assertSame(['name-' . $i, 'changed-' . $id], $projection->processed[$id]);
        }
    }

    /** The messages routed before an error of the main subscription keep their partitions. */
    public function testPartitionsOfMessagesRoutedBeforeAnErrorAreCreated(): void
    {
        $projection = new PartitionedProfileProjection();
        $engine = $this->engine($projection);

        $engine->execute(new Setup(skipBooting: true));

        [$firstId, $secondId, $brokenId] = $this->createProfiles(3);

        $projection->partitionKey = static function (Message $message) use ($brokenId): string {
            $streamName = $message->header(StreamNameHeader::class)->streamName;

            if ($streamName === 'profile-' . $brokenId) {
                throw new RuntimeException('no key');
            }

            return $streamName;
        };

        $engine->execute(new Run());

        self::assertSame(Status::Error, $this->subscriptionStore->get(self::MAIN)->status());
        self::assertSame(
            [
                Subscription::partitionId(self::MAIN, 'profile-' . $firstId),
                Subscription::partitionId(self::MAIN, 'profile-' . $secondId),
            ],
            array_keys($this->partitions()),
        );
    }

    /** The repository that runs the engine after every save, e.g. to read your own writes. */
    public function testProjectionIsUpToDateAfterASynchronousRun(): void
    {
        $projection = new PartitionedProfileProjection();
        $engine = $this->engine($projection);

        $engine->execute(new Setup(skipBooting: true));

        $repository = new RunSubscriptionEngineRepository($this->repository, $engine);

        $id = ProfileId::generate();
        $repository->save(Profile::create($id, 'name'));

        self::assertSame(['name'], $projection->processed[$id->toString()]);
    }

    /** @return iterable<string, array{bool}> */
    public static function sameRun(): iterable
    {
        yield 'routed in the same run' => [true];
        yield 'routed in separate runs' => [false];
    }

    /** MySQL and MariaDB compare strings case insensitive by default, partition keys must not. */
    #[DataProvider('sameRun')]
    public function testKeysThatOnlyDifferInCaseAreDifferentPartitions(bool $sameRun): void
    {
        $projection = new PartitionedProfileProjection();
        $projection->partitionKey = static function (Message $message): string {
            $event = $message->event();

            return $event instanceof ProfileCreated ? 'tenant-' . $event->name : 'other';
        };

        $engine = $this->engine($projection);
        $engine->execute(new Setup(skipBooting: true));

        [$upperId] = $this->createProfiles(1, ['ACME']);

        if (!$sameRun) {
            $this->runUntilFinished($engine);
        }

        [$lowerId] = $this->createProfiles(1, ['acme']);
        $this->runUntilFinished($engine);

        self::assertSame(['ACME'], $projection->processed[$upperId]);
        self::assertSame(['acme'], $projection->processed[$lowerId]);
        $keys = array_map(
            static fn (Subscription $partition): string|null => $partition->partitionKey(),
            array_values($this->partitions()),
        );
        sort($keys);

        self::assertSame(['tenant-ACME', 'tenant-acme'], $keys);
    }

    public function testIdlePartitionsAreRemovedAndCreatedAgain(): void
    {
        $projection = new EphemeralPartitionProjection();
        $projection->failingNames = ['broken'];
        $engine = $this->engine($projection);
        $subscriberId = EphemeralPartitionProjection::SUBSCRIBER_ID;

        $engine->execute(new Setup(skipBooting: true));

        [$idleId, $busyId, $brokenId] = $this->createProfiles(3, ['idle', 'busy', 'broken']);
        $this->runUntilFinished($engine);

        self::assertCount(3, $this->partitions($subscriberId));

        $this->clock->sleep(40);
        $this->changeName(ProfileId::fromString($busyId), 'busy-2');
        $this->runUntilFinished($engine);

        self::assertCount(3, $this->partitions($subscriberId), 'nothing is idle long enough yet');

        $this->clock->sleep(30);
        $this->runUntilFinished($engine);

        $expected = [
            Subscription::partitionId($subscriberId, 'profile-' . $brokenId),
            Subscription::partitionId($subscriberId, 'profile-' . $busyId),
        ];
        sort($expected);

        self::assertSame(
            $expected,
            $this->sortedPartitionIds($subscriberId),
            'the idle partition is removed, a busy one and one in error stay',
        );

        $this->changeName(ProfileId::fromString($idleId), 'idle-2');
        $this->runUntilFinished($engine);

        self::assertSame(['idle', 'idle-2'], $projection->processed[$idleId], 'the next message creates it again');
        self::assertCount(3, $this->partitions($subscriberId));
    }

    /** @return list<string> */
    private function sortedPartitionIds(string $subscriberId): array
    {
        $ids = array_keys($this->partitions($subscriberId));
        sort($ids);

        return $ids;
    }

    /** A partition that processed its inbox before its main subscription finished, finishes afterwards as well. */
    public function testPartitionsOfARunOnceSubscriberFinish(): void
    {
        $projection = new OncePartitionedProjection();
        $engine = $this->engine($projection);
        $subscriberId = OncePartitionedProjection::SUBSCRIBER_ID;

        $engine->execute(new Setup(skipBooting: true));
        $this->createProfiles(3);

        // one message per run, so the first partitions are done long before the main subscription
        $this->runUntilFinished($engine, 1);

        self::assertSame(3, $projection->handled);
        self::assertSame(Status::Finished, $this->subscriptionStore->get($subscriberId)->status());

        foreach ($this->partitions($subscriberId) as $partition) {
            self::assertSame(Status::Finished, $partition->status(), $partition->id());
        }
    }

    /** @param 'index'|'plain'|'unsupported' $messageLoader */
    private function engine(object $subscriber, string $messageLoader = 'index', bool $inboxStore = true): SubscriptionEngine
    {
        $store = $this->store = new StreamDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
        );

        $this->subscriptionStore = new DoctrineSubscriptionStore($this->connection, $this->clock);

        $this->repository = (new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => Profile::class]),
            $store,
        ))->get(Profile::class);

        (new DoctrineSchemaDirector(
            $this->connection,
            new ChainDoctrineSchemaConfigurator([$store, $this->subscriptionStore]),
        ))->create();

        $subscriberRepository = new MetadataSubscriberAccessorRepository([$subscriber]);

        $loader = new EventFilteredStoreMessageLoader($store, new AttributeEventMetadataFactory(), $subscriberRepository);

        return new DefaultSubscriptionEngine(
            match ($messageLoader) {
                'index' => $loader,
                'plain' => new PlainMessageLoader($loader),
                'unsupported' => new IndexCriterionNotSupportedMessageLoader($loader),
            },
            $inboxStore ? $this->subscriptionStore : new PlainSubscriptionStore($this->subscriptionStore),
            $subscriberRepository,
            new RetryStrategyRepository([
                RetryStrategyRepository::DEFAULT_STRATEGY_NAME => new ClockBasedRetryStrategy($this->clock),
            ]),
        );
    }

    /**
     * @param list<string> $names
     *
     * @return list<string> profile ids
     */
    private function createProfiles(int $count, array $names = []): array
    {
        $ids = [];

        for ($i = 0; $i < $count; $i++) {
            $profileId = ProfileId::generate();
            $this->repository->save(Profile::create($profileId, $names[$i] ?? 'name-' . $i));
            $ids[] = $profileId->toString();
        }

        return $ids;
    }

    private function changeName(ProfileId $profileId, string $name): void
    {
        $profile = $this->repository->load($profileId);
        $profile->changeName($name);
        $this->repository->save($profile);
    }

    /**
     * @param positive-int|null $limit
     * @param positive-int      $partitionsPerClaim
     */
    private function runUntilFinished(SubscriptionEngine $engine, int|null $limit = null, int $partitionsPerClaim = 100): void
    {
        for ($i = 0; $i < 50; $i++) {
            $result = $engine->execute(new Run(limit: $limit, partitionsPerClaim: $partitionsPerClaim));
            self::assertInstanceOf(ProcessedResult::class, $result);

            if ($result->finished) {
                return;
            }
        }

        self::fail('The run did not finish.');
    }

    /** @return array<string, Subscription> partition id => partition */
    private function partitions(string $subscriberId = self::MAIN): array
    {
        $partitions = [];

        foreach ($this->subscriptionStore->find(new SubscriptionCriteria(ids: [$subscriberId], partitions: true)) as $partition) {
            $partitions[$partition->id()] = $partition;
        }

        return $partitions;
    }

    public function testEachPartitionInItsOwnClaim(): void
    {
        $projection = new PartitionedProfileProjection();
        $projection->failingNames = ['broken'];
        $engine = $this->engine($projection);

        $engine->execute(new Setup(skipBooting: true));

        [$brokenId, $healthyId, $otherId] = $this->createProfiles(3, ['broken', 'healthy', 'other']);

        $this->runUntilFinished($engine, partitionsPerClaim: 1);

        self::assertSame(['healthy'], $projection->processed[$healthyId]);
        self::assertSame(['other'], $projection->processed[$otherId]);
        self::assertArrayNotHasKey($brokenId, $projection->processed);
    }

    public function testLimitAppliesToEveryPartitionOfAClaim(): void
    {
        $projection = new PartitionedProfileProjection();
        $engine = $this->engine($projection);

        $engine->execute(new Setup(skipBooting: true));

        $ids = $this->createProfiles(5);

        $engine->execute(new Run()); // the main subscription routes everything
        $engine->execute(new Run(limit: 1));

        foreach ($ids as $i => $id) {
            self::assertSame(['name-' . $i], $projection->processed[$id], 'a limit of 1 still lets every partition of the group process a message');
        }
    }

    /** @return iterable<string, array{positive-int}> */
    public static function partitionsPerClaim(): iterable
    {
        yield 'one partition per claim' => [1];
        yield 'partitions in groups' => [100];
    }

    /**
     * Every batch is a transaction on the shared connection. Partition A fails after partition B started its batch:
     * only A may be rolled back, B has to be committed.
     *
     * @param positive-int $partitionsPerClaim
     */
    #[DataProvider('partitionsPerClaim')]
    public function testTransactionalBatchesOfAGroupDoNotOverlap(int $partitionsPerClaim): void
    {
        $engine = $this->engine(new PartitionedTransactionalBatchProjection($this->connection));

        $engine->execute(new Setup(skipBooting: true));

        $a = ProfileId::generate();
        $b = ProfileId::generate();
        $this->repository->save(Profile::create($a, 'A'));
        $this->repository->save(Profile::create($b, 'B'));
        $this->changeName($a, PartitionedTransactionalBatchProjection::POISON);
        $this->changeName($b, 'B2');

        $this->runUntilFinished($engine, partitionsPerClaim: $partitionsPerClaim);

        self::assertSame(
            [['id' => $b->toString(), 'name' => 'B2']],
            $this->connection->fetchAllAssociative('SELECT id, name FROM ' . PartitionedTransactionalBatchProjection::SUBSCRIBER_ID),
        );

        $partitions = $this->partitions(PartitionedTransactionalBatchProjection::SUBSCRIBER_ID);
        $partitionOfA = $partitions[Subscription::partitionId(PartitionedTransactionalBatchProjection::SUBSCRIBER_ID, 'profile-' . $a->toString())];
        $partitionOfB = $partitions[Subscription::partitionId(PartitionedTransactionalBatchProjection::SUBSCRIBER_ID, 'profile-' . $b->toString())];

        self::assertSame(Status::Error, $partitionOfA->status());
        self::assertSame(0, $partitionOfA->position(), 'the failed batch did not move the position');
        self::assertSame(
            [$partitionOfA->id() => [1, 3]],
            $this->subscriptionStore->inbox([$partitionOfA->id() => -1], 10),
            'nothing of the rolled back batch leaves the inbox',
        );
        self::assertSame(Status::Active, $partitionOfB->status());
    }

    public function testFailedPartitionKeepsOnlyTheUnprocessedMessagesInItsInbox(): void
    {
        $projection = new PartitionedProfileProjection();
        $projection->failingNames = ['broken'];
        $engine = $this->engine($projection);

        $engine->execute(new Setup(skipBooting: true));

        $id = ProfileId::generate();
        $this->repository->save(Profile::create($id, 'n1'));
        $this->changeName($id, 'n2');
        $this->changeName($id, 'broken');
        $this->changeName($id, 'n4');

        $this->runUntilFinished($engine);

        $partition = $this->partitions()[Subscription::partitionId(self::MAIN, 'profile-' . $id->toString())];

        self::assertSame(Status::Error, $partition->status());
        self::assertSame(2, $partition->position());
        self::assertSame(['n1', 'n2'], $projection->processed[$id->toString()]);
        self::assertSame(
            [$partition->id() => [3, 4]],
            $this->subscriptionStore->inbox([$partition->id() => -1], 10),
            'the processed messages before the error leave the inbox',
        );

        $projection->failingNames = [];
        $this->clock->sleep(10);
        $this->runUntilFinished($engine);

        self::assertSame(['n1', 'n2', 'broken', 'n4'], $projection->processed[$id->toString()]);
        self::assertSame([], $this->subscriptionStore->inbox([$partition->id() => -1], 10));
    }

    /** @return iterable<string, array{'plain'|'unsupported'}> */
    public static function messageLoadersWithoutIndexSupport(): iterable
    {
        yield 'loader without IndexMessageLoader' => ['plain'];
        yield 'store without IndexCriterion' => ['unsupported'];
    }

    /** @param 'plain'|'unsupported' $messageLoader */
    #[DataProvider('messageLoadersWithoutIndexSupport')]
    public function testMessageLoaderWithoutIndexSupport(string $messageLoader): void
    {
        $projection = new PartitionedProfileProjection();
        $engine = $this->engine($projection, $messageLoader);

        $engine->execute(new Setup(skipBooting: true));

        $ids = $this->createProfiles(3);

        foreach ($ids as $id) {
            $this->changeName(ProfileId::fromString($id), 'changed-' . $id);
        }

        $this->runUntilFinished($engine);

        foreach ($ids as $i => $id) {
            self::assertSame(['name-' . $i, 'changed-' . $id], $projection->processed[$id], 'the partitions read the range of their inbox');
        }

        foreach ($this->partitions() as $partition) {
            self::assertSame([], $this->subscriptionStore->inbox([$partition->id() => -1], 10), $partition->id());
        }
    }

    public function testSubscriptionStoreWithoutInbox(): void
    {
        $projection = new PartitionedProfileProjection();
        $engine = $this->engine($projection, inboxStore: false);

        $engine->execute(new Setup(skipBooting: true));
        $this->createProfiles(1);

        $engine->execute(new Run());

        $main = $this->subscriptionStore->get(self::MAIN);

        $error = $main->subscriptionError();

        self::assertSame(Status::Error, $main->status());
        self::assertNotNull($error);
        self::assertStringContainsString('does not support partitions', $error->errorMessage);
        self::assertSame([], $this->partitions());
    }

    /** Every handler fails, like when the database of the projection is down. */
    public function testCircuitBreakerStopsTheSubscriberOnASystemicFailure(): void
    {
        $projection = new PartitionedProfileProjection();
        $engine = $this->engine($projection);

        $engine->execute(new Setup(skipBooting: true));

        $ids = $this->createProfiles(20);
        $projection->failingNames = array_map(static fn (int $i): string => 'name-' . $i, array_keys($ids));

        $this->runUntilFinished($engine);
        $this->runUntilFinished($engine);

        $main = $this->subscriptionStore->get(self::MAIN);
        $error = $main->subscriptionError();
        self::assertSame(Status::Error, $main->status(), 'one visible error for the whole subscriber');
        self::assertNotNull($error);
        self::assertStringContainsString('Forced failure', $error->errorMessage);

        $partitions = $this->partitions();
        self::assertCount(20, $partitions);

        foreach ($partitions as $partition) {
            self::assertSame(Status::Active, $partition->status(), $partition->id());
            self::assertNull($partition->subscriptionError(), $partition->id());
            self::assertSame(0, $partition->retryAttempt(), $partition->id());
            self::assertCount(1, $this->subscriptionStore->inbox([$partition->id() => -1], 10)[$partition->id()] ?? [], 'the message is still in the inbox');
        }

        self::assertSame(5, $projection->handled, 'only partitions up to the threshold were tried');

        // the database is back
        $projection->failingNames = [];
        $this->clock->sleep(10);
        $this->runUntilFinished($engine);

        self::assertSame(Status::Active, $this->subscriptionStore->get(self::MAIN)->status());
        self::assertSame(0, $this->subscriptionStore->get(self::MAIN)->retryAttempt(), 'successful partitions reset the retries of the main subscription');

        foreach ($ids as $i => $id) {
            self::assertSame(['name-' . $i], $projection->processed[$id]);
        }
    }

    public function testSparseFailuresDoNotTripTheCircuitBreaker(): void
    {
        $projection = new PartitionedProfileProjection();
        $projection->failingNames = ['name-3', 'name-11'];
        $engine = $this->engine($projection);

        $engine->execute(new Setup(skipBooting: true));

        $ids = $this->createProfiles(20);
        $this->runUntilFinished($engine);

        self::assertSame(Status::Active, $this->subscriptionStore->get(self::MAIN)->status());

        $failed = [];

        foreach ($this->partitions() as $partition) {
            if (!$partition->isError()) {
                continue;
            }

            $failed[] = $partition->id();
        }

        self::assertCount(2, $failed);
        self::assertCount(18, $projection->processed);
        self::assertArrayNotHasKey($ids[3], $projection->processed);
    }

    public function testPersistentOutageEventuallyFailsTheSubscriber(): void
    {
        $projection = new PartitionedProfileProjection();
        $engine = $this->engine($projection);

        $engine->execute(new Setup(skipBooting: true));

        $ids = $this->createProfiles(10);
        $projection->failingNames = array_map(static fn (int $i): string => 'name-' . $i, array_keys($ids));

        for ($i = 0; $i < 10; $i++) {
            // new events keep coming in during the outage, the main subscription routes them successfully
            $projection->failingNames[] = 'outage-' . $i;
            $this->repository->save(Profile::create(ProfileId::generate(), 'outage-' . $i));

            $engine->execute(new Run());
            $engine->execute(new Run());
            $this->clock->sleep(1000);
        }

        self::assertSame(Status::Failed, $this->subscriptionStore->get(self::MAIN)->status(), 'the retries of the main subscription are used up');

        foreach ($this->partitions() as $partition) {
            self::assertSame(Status::Active, $partition->status(), 'the partitions did not use up their retries');
            self::assertSame(0, $partition->retryAttempt());
        }
    }
}
