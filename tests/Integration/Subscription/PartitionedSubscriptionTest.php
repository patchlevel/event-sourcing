<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Clock\FrozenClock;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Schema\ChainDoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
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
use Patchlevel\EventSourcing\Subscription\SubscriptionType;
use Patchlevel\EventSourcing\Tests\DbalManager;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber\PartitionedProfileProjection;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function array_unique;
use function array_values;
use function crc32;
use function gc_collect_cycles;
use function sort;

#[CoversNothing]
final class PartitionedSubscriptionTest extends TestCase
{
    private const SUBSCRIBER_ID = 'partitioned_profile';

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

    public function testDiscovery(): void
    {
        [$engine, $repository, $subscriptionStore] = $this->bootstrap();

        $engine->execute(new Setup(skipBooting: true));

        $firstIndex = [];

        for ($i = 1; $i <= 8; $i++) {
            $profileId = ProfileId::generate();
            $repository->save(Profile::create($profileId, 'name-' . $i));
            $firstIndex[PartitionedProfileProjection::partitionKey($profileId->toString())] ??= $i;
        }

        $result = $engine->execute(new Run());

        self::assertInstanceOf(ProcessedResult::class, $result);
        self::assertFalse($result->finished, 'new partitions still have to be processed');

        $partitions = $this->partitionsByKey($subscriptionStore);
        self::assertSame($this->sorted(array_keys($firstIndex)), $this->sorted(array_keys($partitions)));

        foreach ($firstIndex as $key => $index) {
            $partition = $partitions[$key];

            self::assertSame(self::SUBSCRIBER_ID . '#' . $key, $partition->id());
            self::assertSame(self::SUBSCRIBER_ID, $partition->subscriberId());
            self::assertSame(SubscriptionType::Partition, $partition->type());
            self::assertSame($index - 1, $partition->position(), 'partition starts right before its first event');
            self::assertSame(Status::Active, $partition->status());
            self::assertSame(crc32($key) % 2, $partition->shard());
            self::assertNull($partition->cleanupTasks());
        }

        $root = $subscriptionStore->get(self::SUBSCRIBER_ID);
        self::assertSame(SubscriptionType::Main, $root->type());
        self::assertSame(Status::Active, $root->status());
        self::assertSame(8, $root->position());
        self::assertNull($root->shard());
    }

    public function testEachPartitionProcessesOnlyItsOwnEvents(): void
    {
        [$engine, $repository, , $projection] = $this->bootstrap();

        $engine->execute(new Setup(skipBooting: true));

        $ids = [];

        for ($i = 0; $i < 8; $i++) {
            $profileId = ProfileId::generate();
            $repository->save(Profile::create($profileId, 'name-' . $i));
            $ids[] = $profileId->toString();
        }

        $engine->execute(new Run());
        $engine->execute(new Run());

        self::assertSame($this->sorted($ids), $this->sorted(array_keys($projection->processed)));

        foreach ($ids as $i => $id) {
            self::assertSame(['name-' . $i], $projection->processed[$id]);
        }
    }

    public function testBootProcessesDiscoveredPartitions(): void
    {
        [$engine, $repository, $subscriptionStore, $projection] = $this->bootstrap();

        $ids = [];

        for ($i = 0; $i < 4; $i++) {
            $profileId = ProfileId::generate();
            $repository->save(Profile::create($profileId, 'name-' . $i));
            $ids[] = $profileId->toString();
        }

        $engine->execute(new Setup());

        for ($i = 0; $i < 5; $i++) {
            $result = $engine->execute(new Boot());

            self::assertInstanceOf(ProcessedResult::class, $result);

            if ($result->finished) {
                break;
            }
        }

        self::assertSame($this->sorted($ids), $this->sorted(array_keys($projection->processed)));

        foreach ($subscriptionStore->find() as $subscription) {
            self::assertSame(Status::Active, $subscription->status(), $subscription->id());
        }
    }

    public function testBootIsNotFinishedWhileDiscoveredPartitionsAreBooting(): void
    {
        [$engine, $repository, $subscriptionStore, $projection] = $this->bootstrap();

        $ids = [];

        for ($i = 0; $i < 3; $i++) {
            $profileId = ProfileId::generate();
            $repository->save(Profile::create($profileId, 'name-' . $i));
            $ids[] = $profileId->toString();
        }

        $failingId = ProfileId::generate();
        $repository->save(Profile::create($failingId, 'failing'));
        $projection->failingPartitionKeys = [PartitionedProfileProjection::partitionKey($failingId->toString()) => true];

        $engine->execute(new Setup());

        $result = $engine->execute(new Boot());

        self::assertInstanceOf(ProcessedResult::class, $result);
        self::assertFalse($result->finished, 'discovered partitions are still booting');
        self::assertSame(Status::Error, $subscriptionStore->get(self::SUBSCRIBER_ID)->status());

        $result = $engine->execute(new Boot());

        self::assertInstanceOf(ProcessedResult::class, $result);
        self::assertTrue($result->finished);
        self::assertSame($this->sorted($ids), $this->sorted(array_keys($projection->processed)));

        foreach ($this->partitionsByKey($subscriptionStore) as $partition) {
            self::assertSame(Status::Active, $partition->status(), $partition->id());
        }
    }

    public function testFaultIsolationAndRetry(): void
    {
        [$engine, $repository, $subscriptionStore, $projection, $clock] = $this->bootstrap();

        $engine->execute(new Setup(skipBooting: true));

        $failId = ProfileId::generate();
        $okId = ProfileId::generate();

        $repository->save(Profile::create($failId, 'broken'));
        $repository->save(Profile::create($okId, 'healthy'));

        $projection->failing = [$failId->toString()];

        $engine->execute(new Run());
        $engine->execute(new Run());

        self::assertSame(['healthy'], $projection->processed[$okId->toString()]);
        self::assertArrayNotHasKey($failId->toString(), $projection->processed);

        $partitions = $this->partitionsByKey($subscriptionStore);
        $failKey = PartitionedProfileProjection::partitionKey($failId->toString());
        $okKey = PartitionedProfileProjection::partitionKey($okId->toString());

        self::assertSame(Status::Active, $partitions[$okKey]->status());
        self::assertSame(Status::Error, $partitions[$failKey]->status());
        self::assertSame(Status::Active, $subscriptionStore->get(self::SUBSCRIBER_ID)->status());

        $projection->failing = [];
        $clock->sleep(10);

        $engine->execute(new Run());

        self::assertSame(['broken'], $projection->processed[$failId->toString()]);
        self::assertSame(['healthy'], $projection->processed[$okId->toString()]);
        self::assertSame(Status::Active, $this->partitionsByKey($subscriptionStore)[$failKey]->status());
    }

    public function testPerStreamOrdering(): void
    {
        [$engine, $repository, , $projection] = $this->bootstrap();

        $engine->execute(new Setup(skipBooting: true));

        $profileId = ProfileId::generate();
        $profile = Profile::create($profileId, 'John');
        $profile->changeName('Jane');
        $profile->changeName('Jack');
        $repository->save($profile);

        $engine->execute(new Run());
        $engine->execute(new Run());

        self::assertSame(['John', 'Jane', 'Jack'], $projection->processed[$profileId->toString()]);
    }

    public function testRunOnlyGivenShards(): void
    {
        [$engine, $repository, $subscriptionStore, $projection] = $this->bootstrap();

        $engine->execute(new Setup(skipBooting: true));

        $idsByShard = [0 => [], 1 => []];

        for ($i = 0; $i < 16; $i++) {
            $profileId = ProfileId::generate();
            $repository->save(Profile::create($profileId, 'name-' . $i));
            $idsByShard[crc32(PartitionedProfileProjection::partitionKey($profileId->toString())) % 2][] = $profileId->toString();
        }

        // the root has no shard, so it discovers the partitions of every shard
        $engine->execute(new Run(shards: [0]));
        $engine->execute(new Run(shards: [0]));

        self::assertSame($this->sorted($idsByShard[0]), $this->sorted(array_keys($projection->processed)));

        $engine->execute(new Run(shards: [1]));

        self::assertSame(
            $this->sorted([...$idsByShard[0], ...$idsByShard[1]]),
            $this->sorted(array_keys($projection->processed)),
        );

        $shards = [];

        foreach ($this->partitionsByKey($subscriptionStore) as $partition) {
            $shards[] = $partition->shard();
        }

        self::assertSame([0, 1], $this->sorted(array_values(array_unique($shards))));
    }

    public function testChangedShardsAreDetected(): void
    {
        [$engine, $repository, $subscriptionStore] = $this->bootstrap();

        $engine->execute(new Setup(skipBooting: true));

        $repository->save(Profile::create(ProfileId::generate(), 'John'));

        $engine->execute(new Run());

        // simulate partitions created with a different number of shards
        $this->connection->executeStatement(
            'UPDATE subscriptions SET shard = 5 WHERE type = :type',
            ['type' => SubscriptionType::Partition->value],
        );

        $repository->save(Profile::create(ProfileId::generate(), 'Jane'));

        $engine->execute(new Run());

        foreach ($subscriptionStore->find() as $subscription) {
            self::assertSame(Status::Error, $subscription->status(), $subscription->id());
            self::assertStringContainsString(
                'number of shards',
                (string)$subscription->subscriptionError()?->errorMessage,
            );
        }
    }

    public function testRemoveCascadesToPartitions(): void
    {
        [$engine, $repository, $subscriptionStore] = $this->bootstrap();

        $engine->execute(new Setup(skipBooting: true));

        for ($i = 0; $i < 8; $i++) {
            $repository->save(Profile::create(ProfileId::generate(), 'name-' . $i));
        }

        $engine->execute(new Run());

        self::assertNotEmpty($this->partitionsByKey($subscriptionStore));

        $engine->execute(new Remove(ids: [self::SUBSCRIBER_ID]));

        self::assertSame([], $subscriptionStore->find());
    }

    /**
     * @return array{
     *     SubscriptionEngine,
     *     Repository<Profile>,
     *     DoctrineSubscriptionStore,
     *     PartitionedProfileProjection,
     *     FrozenClock
     * }
     */
    private function bootstrap(): array
    {
        $store = new StreamDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
        );

        $clock = new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00'));

        $subscriptionStore = new DoctrineSubscriptionStore($this->connection, $clock);

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => Profile::class]),
            $store,
        );

        $repository = $manager->get(Profile::class);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            new ChainDoctrineSchemaConfigurator([$store, $subscriptionStore]),
        );

        $schemaDirector->create();

        $projection = new PartitionedProfileProjection();
        $subscriberRepository = new MetadataSubscriberAccessorRepository([$projection]);

        $engine = new DefaultSubscriptionEngine(
            new EventFilteredStoreMessageLoader($store, new AttributeEventMetadataFactory(), $subscriberRepository),
            $subscriptionStore,
            $subscriberRepository,
            new RetryStrategyRepository([
                RetryStrategyRepository::DEFAULT_STRATEGY_NAME => new ClockBasedRetryStrategy($clock),
            ]),
        );

        return [$engine, $repository, $subscriptionStore, $projection, $clock];
    }

    /** @return array<string, Subscription> partition key => partition subscription */
    private function partitionsByKey(DoctrineSubscriptionStore $store): array
    {
        $partitions = [];

        $criteria = new SubscriptionCriteria(
            subscriberIds: [self::SUBSCRIBER_ID],
            types: [SubscriptionType::Partition],
        );

        foreach ($store->find($criteria) as $partition) {
            $partitions[(string)$partition->partitionKey()] = $partition;
        }

        return $partitions;
    }

    /**
     * @param list<T> $values
     *
     * @return list<T>
     *
     * @template T
     */
    private function sorted(array $values): array
    {
        sort($values);

        return $values;
    }
}
