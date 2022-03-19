<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark;

use Doctrine\DBAL\Driver\PDO\SQLite\Driver;
use Doctrine\DBAL\DriverManager;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\Repository\SnapshotRepository;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaManager;
use Patchlevel\EventSourcing\Snapshot\InMemorySnapshotStore;
use Patchlevel\EventSourcing\Store\SingleTableStore;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Aggregate\Profile;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\ProfileId;
use PhpBench\Attributes as Bench;

use function file_exists;
use function unlink;

#[Bench\BeforeMethods('setUp')]
final class LoadEventsWithSnapshotsBench
{
    private const DB_PATH = __DIR__ . '/BasicImplementation/data/db.sqlite3';

    private Store $store;
    private EventBus $bus;
    private InMemorySnapshotStore $snapshotStore;

    public function setUp(): void
    {
        if (file_exists(self::DB_PATH)) {
            unlink(self::DB_PATH);
        }

        $connection = DriverManager::getConnection([
            'driverClass' => Driver::class,
            'path' => self::DB_PATH,
        ]);

        $this->bus = new DefaultEventBus();

        $this->store = new SingleTableStore(
            $connection,
            [Profile::class => 'profile'],
            'eventstore'
        );

        $this->snapshotStore = new InMemorySnapshotStore();
        $repository = new SnapshotRepository($this->store, $this->bus, Profile::class, $this->snapshotStore);

        // create tables
        (new DoctrineSchemaManager())->create($this->store);

        $profile = Profile::create(ProfileId::fromString('1'), 'Peter');

        for ($i = 0; $i < 10_000; $i++) {
            $profile->changeName('Peter');
        }

        $repository->save($profile);
    }

    #[Bench\Revs(10)]
    #[Bench\Iterations(2)]
    public function benchLoadEvents(): void
    {
        $repository = new SnapshotRepository($this->store, $this->bus, Profile::class, $this->snapshotStore);

        $repository->load('1');
    }
}
