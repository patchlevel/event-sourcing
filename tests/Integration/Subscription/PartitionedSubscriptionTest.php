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
use Patchlevel\EventSourcing\Subscription\RetryStrategy\ClockBasedRetryStrategy;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\RetryStrategyRepository;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\DoctrineSubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Tests\DbalManager;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber\PartitionedBatchProfileProjection;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber\PartitionedProfileProjection;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

use function array_keys;
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
        self::assertFalse($result->finished, 'the routed messages still have to be processed by the partitions');

        self::assertSame(0, $projection->handled, 'the main subscription never calls the subscriber');

        $partitions = $this->partitions();
        self::assertCount(3, $partitions);

        foreach ($ids as $id) {
            $partition = $partitions[Subscription::partitionId(self::MAIN, 'profile-' . $id)];

            self::assertSame(self::MAIN, $partition->subscriberId());
            self::assertSame('profile-' . $id, $partition->partitionKey());
            self::assertSame(Status::Active, $partition->status());
            self::assertCount(1, $this->subscriptionStore->inbox([$partition->id() => -1], 10)[$partition->id()] ?? []);
        }

        $this->runUntilFinished($engine);

        foreach ($ids as $i => $id) {
            self::assertSame(['name-' . $i], $projection->processed[$id]);
        }

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

    private function engine(object $subscriber): SubscriptionEngine
    {
        $store = new StreamDoctrineDbalStore(
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

        return new DefaultSubscriptionEngine(
            new EventFilteredStoreMessageLoader($store, new AttributeEventMetadataFactory(), $subscriberRepository),
            $this->subscriptionStore,
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
}
