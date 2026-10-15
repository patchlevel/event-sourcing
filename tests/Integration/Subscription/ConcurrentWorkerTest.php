<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Schema\ChainDoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\StreamDoctrineDbalStore;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Setup;
use Patchlevel\EventSourcing\Subscription\Engine\DefaultSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\EventFilteredStoreMessageLoader;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\DoctrineSubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Tests\DbalManager;
use Patchlevel\EventSourcing\Tests\Integration\Subscription\Subscriber\ConcurrentProfileProjection;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_map;
use function fclose;
use function file_exists;
use function getenv;
use function is_numeric;
use function is_resource;
use function microtime;
use function proc_close;
use function proc_open;
use function range;
use function stream_get_contents;
use function sys_get_temp_dir;
use function tempnam;
use function touch;
use function unlink;
use function usleep;

use const PHP_BINARY;

/** Two real worker processes process a partitioned subscriber while new events keep coming in. */
#[CoversNothing]
final class ConcurrentWorkerTest extends TestCase
{
    private const AGGREGATES = 40;
    private const EVENTS_PER_AGGREGATE = 10;

    private Connection $connection;
    private string $stopFile;

    /** @var array<string, array{resource, array<int, resource>}> */
    private array $workers = [];

    public function setUp(): void
    {
        $this->connection = DbalManager::createConnection();

        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            self::markTestSkipped('needs a database that several processes can share');
        }

        $stopFile = tempnam(sys_get_temp_dir(), 'partition-worker-stop');

        if ($stopFile === false) {
            throw new RuntimeException('no temp file');
        }

        unlink($stopFile);
        $this->stopFile = $stopFile;
    }

    public function tearDown(): void
    {
        if (isset($this->stopFile) && !file_exists($this->stopFile)) {
            touch($this->stopFile);
        }

        foreach ($this->workers as [$process, $pipes]) {
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }

            proc_close($process);
        }

        if (isset($this->stopFile) && file_exists($this->stopFile)) {
            unlink($this->stopFile);
        }

        $this->connection->close();
    }

    public function testTwoWorkersProcessEveryMessageOnceAndInOrder(): void
    {
        $store = new StreamDoctrineDbalStore($this->connection, DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']));
        $subscriptionStore = new DoctrineSubscriptionStore($this->connection);

        (new DoctrineSchemaDirector(
            $this->connection,
            new ChainDoctrineSchemaConfigurator([$store, $subscriptionStore]),
        ))->create();

        $table = new Table(ConcurrentProfileProjection::TABLE);
        $table->addColumn('id', Types::INTEGER)->setAutoincrement(true);
        $table->addColumn('profile_id', Types::STRING)->setLength(36);
        $table->addColumn('name', Types::STRING)->setLength(32);
        $table->addColumn('worker', Types::STRING)->setLength(16);
        $table->setPrimaryKey(['id']);
        $this->connection->createSchemaManager()->createTable($table);

        $subscriberRepository = new MetadataSubscriberAccessorRepository([
            new ConcurrentProfileProjection($this->connection, 'test'),
        ]);

        (new DefaultSubscriptionEngine(
            new EventFilteredStoreMessageLoader($store, new AttributeEventMetadataFactory(), $subscriberRepository),
            $subscriptionStore,
            $subscriberRepository,
        ))->execute(new Setup(skipBooting: true));

        $this->startWorker('worker-a');
        $this->startWorker('worker-b');

        /** @var Repository<Profile> $repository */
        $repository = (new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => Profile::class]),
            $store,
        ))->get(Profile::class);

        // the events keep coming in while the workers run, round robin over the aggregates
        $ids = array_map(static fn (): ProfileId => ProfileId::generate(), range(1, self::AGGREGATES));

        foreach ($ids as $id) {
            $repository->save(Profile::create($id, 'n0'));
        }

        for ($i = 1; $i < self::EVENTS_PER_AGGREGATE; $i++) {
            foreach ($ids as $id) {
                $profile = $repository->load($id);
                $profile->changeName('n' . $i);
                $repository->save($profile);
            }
        }

        $this->waitForRows(self::AGGREGATES * self::EVENTS_PER_AGGREGATE);

        touch($this->stopFile);
        $outputs = $this->stopWorkers();

        foreach ($outputs as $worker => [$exitCode, $stdout, $stderr]) {
            self::assertSame(0, $exitCode, $worker . ': ' . $stdout . $stderr);
        }

        /** @var list<array{profile_id: string, name: string, worker: string}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT profile_id, name, worker FROM ' . ConcurrentProfileProjection::TABLE . ' ORDER BY id',
        );

        self::assertCount(self::AGGREGATES * self::EVENTS_PER_AGGREGATE, $rows, 'every message exactly once');

        $names = [];
        $workers = [];

        foreach ($rows as $row) {
            $names[$row['profile_id']][] = $row['name'];
            $workers[$row['worker']] = true;
        }

        $expected = array_map(static fn (int $i): string => 'n' . $i, range(0, self::EVENTS_PER_AGGREGATE - 1));

        foreach ($ids as $id) {
            self::assertSame($expected, $names[$id->toString()] ?? [], 'in order: ' . $id->toString());
        }

        self::assertCount(2, $workers, 'both workers processed messages');

        $subscriptions = $subscriptionStore->find(new SubscriptionCriteria(ids: [ConcurrentProfileProjection::SUBSCRIBER_ID]));
        self::assertCount(self::AGGREGATES + 1, $subscriptions);

        foreach ($subscriptions as $subscription) {
            self::assertSame(Status::Active, $subscription->status(), $subscription->id());
        }

        self::assertSame(0, $this->rows('subscriptions_inbox'), 'every inbox is processed');
    }

    private function startWorker(string $name): void
    {
        $process = proc_open(
            [PHP_BINARY, __DIR__ . '/../Worker/partition-worker.php', $name, $this->stopFile],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            getenv(),
        );

        if (!is_resource($process)) {
            throw new RuntimeException('worker could not be started');
        }

        $this->workers[$name] = [$process, $pipes];
    }

    private function waitForRows(int $expected): void
    {
        $deadline = microtime(true) + 60;

        while (microtime(true) < $deadline) {
            if ($this->rows(ConcurrentProfileProjection::TABLE) >= $expected) {
                return;
            }

            usleep(50_000);
        }

        self::fail('the workers did not process all messages in time');
    }

    private function rows(string $table): int
    {
        $count = $this->connection->fetchOne('SELECT COUNT(*) FROM ' . $table);

        return is_numeric($count) ? (int)$count : 0;
    }

    /** @return array<string, array{int, string, string}> worker => exit code, stdout, stderr */
    private function stopWorkers(): array
    {
        $outputs = [];

        foreach ($this->workers as $name => [$process, $pipes]) {
            $stdout = (string)stream_get_contents($pipes[1]);
            $stderr = (string)stream_get_contents($pipes[2]);

            foreach ($pipes as $pipe) {
                fclose($pipe);
            }

            $outputs[$name] = [proc_close($process), $stdout, $stderr];
        }

        $this->workers = [];

        return $outputs;
    }
}
