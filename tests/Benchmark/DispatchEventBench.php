<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark;

use Doctrine\DBAL\Driver\PDO\SQLite\Driver;
use Doctrine\DBAL\DriverManager;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\Projection\DefaultProjectionRepository;
use Patchlevel\EventSourcing\Projection\ProjectionListener;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaManager;
use Patchlevel\EventSourcing\Store\SingleTableStore;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Aggregate\Profile;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Projection\ProfileProjection;
use PhpBench\Benchmark\Metadata\Annotations\Iterations;
use PhpBench\Benchmark\Metadata\Annotations\Revs;

/**
 * @\PhpBench\Benchmark\Metadata\Annotations\BeforeMethods({"setUp"})
 */
final class DispatchEventBench
{
    private const DB_PATH = __DIR__ . '/BasicImplementation/data/db.sqlite3';

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
        $projectionRepository = new DefaultProjectionRepository(
            [$profileProjection]
        );

        $eventStream = new DefaultEventBus();
        $eventStream->addListener(new ProjectionListener($projectionRepository));

        $store = new SingleTableStore(
            $connection,
            [Profile::class => 'profile'],
            'eventstore'
        );

        $this->repository = new Repository($store, $eventStream, Profile::class);

        // create tables
        $profileProjection->create();
        (new DoctrineSchemaManager())->create($store);

        $this->profile = Profile::create('1', 'Peter');
        $this->repository->save($this->profile);
    }

    /**
     * @Revs(10)
     * @Iterations(5)
     */
    public function benchSaveOneEvent(): void
    {
        $this->profile->changeName('Peter');
        $this->repository->save($this->profile);
    }

    /**
     * @Revs(10)
     * @Iterations(5)
     */
    public function benchSaveAfterThousandEvents(): void
    {
        for ($i = 0; $i < 1000; $i++) {
            $this->profile->changeName('Peter');
        }

        $this->repository->save($this->profile);
    }
}
