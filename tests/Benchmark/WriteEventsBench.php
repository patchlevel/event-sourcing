<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark;

use Doctrine\DBAL\Driver\PDO\SQLite\Driver;
use Doctrine\DBAL\DriverManager;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\Projection\DefaultProjectionHandler;
use Patchlevel\EventSourcing\Projection\ProjectionListener;
use Patchlevel\EventSourcing\Repository\DefaultRepository;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaManager;
use Patchlevel\EventSourcing\Store\SingleTableStore;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Aggregate\Profile;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Processor\SendEmailProcessor;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\ProfileId;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Projection\ProfileProjection;
use PhpBench\Attributes as Bench;

use function file_exists;
use function unlink;

#[Bench\BeforeMethods('setUp')]
final class WriteEventsBench
{
    private const DB_PATH = __DIR__ . '/BasicImplementation/data/db.sqlite3';

    private Store $store;
    private EventBus $bus;
    private Repository $repository;
    private Profile $profile;

    public function setUp(): void
    {
        if (file_exists(self::DB_PATH)) {
            unlink(self::DB_PATH);
        }

        $connection = DriverManager::getConnection([
            'driverClass' => Driver::class,
            'path' => self::DB_PATH,
        ]);

        $profileProjection = new ProfileProjection($connection);
        $projectionRepository = new DefaultProjectionHandler(
            [$profileProjection]
        );

        $this->bus = new DefaultEventBus();
        $this->bus->addListener(new ProjectionListener($projectionRepository));
        $this->bus->addListener(new SendEmailProcessor());

        $this->store = new SingleTableStore(
            $connection,
            [Profile::class => 'profile'],
            'eventstore'
        );

        $this->repository = new DefaultRepository($this->store, $this->bus, Profile::class);

        // create tables
        $profileProjection->create();
        (new DoctrineSchemaManager())->create($this->store);

        $this->profile = Profile::create(ProfileId::fromString('1'), 'Peter');
        $this->repository->save($this->profile);
    }

    #[Bench\Revs(10)]
    #[Bench\Iterations(2)]
    public function benchSaveOneEvent(): void
    {
        $this->profile->changeName('Peter');
        $this->repository->save($this->profile);
    }

    #[Bench\Revs(10)]
    #[Bench\Iterations(2)]
    public function benchSaveAfterThousandEvents(): void
    {
        for ($i = 0; $i < 1_000; $i++) {
            $this->profile->changeName('Peter');
        }

        $this->repository->save($this->profile);
    }
}
