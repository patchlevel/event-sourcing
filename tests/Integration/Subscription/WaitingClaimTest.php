<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Store\InMemoryStore;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Pause;
use Patchlevel\EventSourcing\Subscription\Engine\DefaultSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\StoreMessageLoader;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\DoctrineSubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Tests\DbalManager;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_map;
use function fclose;
use function file_exists;
use function getenv;
use function is_resource;
use function microtime;
use function proc_close;
use function proc_open;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;
use function usleep;

use const PHP_BINARY;

/** Commands like pause wait for a subscription that a worker is processing, processing leaves it out. */
#[CoversNothing]
final class WaitingClaimTest extends TestCase
{
    private Connection $connection;
    private DoctrineSubscriptionStore $store;

    /** @var list<resource> */
    private array $processes = [];

    public function setUp(): void
    {
        $this->connection = DbalManager::createConnection();

        if ($this->connection->getDatabasePlatform() instanceof SQLitePlatform) {
            self::markTestSkipped('needs a database that several processes can share');
        }

        $this->store = new DoctrineSubscriptionStore($this->connection);
        (new DoctrineSchemaDirector($this->connection, $this->store))->create();
    }

    public function tearDown(): void
    {
        foreach ($this->processes as $process) {
            proc_close($process);
        }

        $this->connection->close();
    }

    public function testClaimWaitsForALockedSubscription(): void
    {
        $this->store->add(new Subscription('profile', status: Status::Active, position: 0));

        $this->lock('profile', 1.0);

        $criteria = new SubscriptionCriteria(status: [Status::Active]);

        $this->connection->beginTransaction();
        self::assertSame([], $this->store->claim(['profile'], $criteria), 'processing leaves it out');
        $this->connection->commit();

        $start = microtime(true);
        $this->connection->beginTransaction();
        $claimed = $this->store->claim(['profile'], $criteria, wait: true);
        $this->connection->commit();

        self::assertSame(['profile'], array_map(static fn (Subscription $subscription): string => $subscription->id(), $claimed));
        self::assertGreaterThan(0.3, microtime(true) - $start, 'waited for the lock');
    }

    public function testPauseReachesALockedPartition(): void
    {
        $main = new Subscription('profile', status: Status::Active, position: 10);
        $partition = Subscription::partition($main, 'a', 5);
        $this->store->add($main, $partition, Subscription::partition($main, 'b', 6));

        $this->lock($partition->id(), 1.0);

        $engine = new DefaultSubscriptionEngine(
            new StoreMessageLoader(new InMemoryStore()),
            $this->store,
            new MetadataSubscriberAccessorRepository([]),
        );

        $engine->execute(new Pause(['profile']));

        foreach ($this->store->find(new SubscriptionCriteria(ids: ['profile'])) as $subscription) {
            self::assertSame(Status::Paused, $subscription->status(), $subscription->id());
        }
    }

    /** Holds the row lock in another process, like a worker that is processing the subscription. */
    private function lock(string $id, float $seconds): void
    {
        $readyFile = tempnam(sys_get_temp_dir(), 'subscription-lock');

        if ($readyFile === false) {
            throw new RuntimeException('no temp file');
        }

        unlink($readyFile);

        $process = proc_open(
            [PHP_BINARY, __DIR__ . '/../Worker/lock-subscription.php', $id, (string)$seconds, $readyFile],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            getenv(),
        );

        if (!is_resource($process)) {
            throw new RuntimeException('lock process could not be started');
        }

        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        $this->processes[] = $process;

        $deadline = microtime(true) + 10;

        while (!file_exists($readyFile)) {
            if (microtime(true) > $deadline) {
                self::fail('the lock process did not start');
            }

            usleep(10_000);
        }

        unlink($readyFile);
    }
}
