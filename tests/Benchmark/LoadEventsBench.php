<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark;

use Doctrine\DBAL\Driver\PDO\SQLite\Driver;
use Doctrine\DBAL\DriverManager;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\Repository\DefaultRepository;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaManager;
use Patchlevel\EventSourcing\Store\SingleTableStore;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Aggregate\Profile;
use PhpBench\Attributes as Bench;

#[Bench\BeforeMethods("setUp")]
final class LoadEventsBench
{
    private const DB_PATH = __DIR__ . '/BasicImplementation/data/db.sqlite3';

    private Store $store;
    private EventBus $bus;

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

        $repository = new DefaultRepository($this->store, $this->bus, Profile::class);

        // create tables
        (new DoctrineSchemaManager())->create($this->store);

        $profile = Profile::create('1', 'Peter');

        for ($i = 0; $i < 10_000; $i++) {
            $profile->changeName('Peter');
        }

        $repository->save($profile);
    }

    #[Bench\Revs(10)]
    #[Bench\Iterations(2)]
    public function benchLoadEvents(): void
    {
        $repository = new DefaultRepository($this->store, $this->bus, Profile::class);

        $repository->load('1');
    }
}