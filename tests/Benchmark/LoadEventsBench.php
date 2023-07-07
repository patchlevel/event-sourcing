<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark;

use Doctrine\DBAL\Driver\PDO\SQLite\Driver;
use Doctrine\DBAL\DriverManager;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AttributeAggregateRootRegistryFactory;
use Patchlevel\EventSourcing\Repository\DefaultRepository;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\DoctrineDbalStore;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Aggregate\Profile;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\ProfileId;
use PhpBench\Attributes as Bench;

use function file_exists;
use function unlink;

#[Bench\BeforeMethods('setUp')]
final class LoadEventsBench
{
    private const DB_PATH = __DIR__ . '/BasicImplementation/data/db.sqlite3';

    private Store $store;
    private EventBus $bus;
    private Repository $repository;

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

        $this->store = new DoctrineDbalStore(
            $connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/BasicImplementation/Events']),
            (new AttributeAggregateRootRegistryFactory())->create([__DIR__ . '/BasicImplementation/Aggregate']),
            'eventstore',
        );

        $this->repository = new DefaultRepository($this->store, $this->bus, Profile::metadata());

        $schemaDirector = new DoctrineSchemaDirector(
            $connection,
            $this->store,
        );

        $schemaDirector->create();

        $profile = Profile::create(ProfileId::fromString('1'), 'Peter');

        for ($i = 0; $i < 10_000; $i++) {
            $profile->changeName('Peter');
        }

        $this->repository->save($profile);
    }

    #[Bench\Revs(10)]
    #[Bench\Iterations(2)]
    public function benchLoadEvents(): void
    {
        $this->repository->load('1');
    }
}
