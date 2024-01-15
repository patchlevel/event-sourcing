<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark;

use Doctrine\DBAL\Driver\PDO\SQLite\Driver;
use Doctrine\DBAL\DriverManager;
use Patchlevel\EventSourcing\Aggregate\AggregateRootId;
use Patchlevel\EventSourcing\EventBus\Decorator\SplitStreamDecorator;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AttributeAggregateRootRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Repository\DefaultRepository;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Snapshot\SnapshotStore;
use Patchlevel\EventSourcing\Store\DoctrineDbalStore;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Aggregate\Profile;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\ProfileId;
use PhpBench\Attributes as Bench;

use function file_exists;
use function sprintf;
use function unlink;

#[Bench\BeforeMethods('setUp')]
final class SplitStreamBench
{
    private const DB_PATH = __DIR__ . '/BasicImplementation/data/db.sqlite3';

    private Store $store;
    private EventBus $bus;
    private SnapshotStore $snapshotStore;
    private Repository $repository;

    private AggregateRootId $id;

    public function setUp(): void
    {
        if (file_exists(self::DB_PATH)) {
            unlink(self::DB_PATH);
        }

        $connection = DriverManager::getConnection([
            'driverClass' => Driver::class,
            'path' => self::DB_PATH,
        ]);

        $this->bus = DefaultEventBus::create();

        $this->store = new DoctrineDbalStore(
            $connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/BasicImplementation/Events']),
            (new AttributeAggregateRootRegistryFactory())->create([__DIR__ . '/BasicImplementation/Aggregate']),
            'eventstore',
        );

        $this->repository = new DefaultRepository(
            $this->store,
            $this->bus,
            Profile::metadata(),
            null,
            new SplitStreamDecorator(
                new AttributeEventMetadataFactory(),
            ),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $connection,
            $this->store,
        );

        $schemaDirector->create();

        $this->id = ProfileId::v7();
    }

    public function provideData(): void
    {
        $profile = Profile::create($this->id, 'Peter');

        for ($i = 0; $i < 10_000; $i++) {
            $profile->changeName(sprintf('Peter %d', $i));

            if ($i % 100 !== 0) {
                continue;
            }

            $profile->reborn();
        }

        $this->repository->save($profile);
    }

    #[Bench\Revs(20)]
    #[Bench\BeforeMethods('provideData')]
    public function benchLoad10000Events(): void
    {
        $this->repository->load($this->id);
    }

    #[Bench\Revs(20)]
    public function benchSave10000Events(): void
    {
        $profile = Profile::create(ProfileId::v7(), 'Peter');

        for ($i = 0; $i < 10_000; $i++) {
            $profile->changeName(sprintf('Peter %d', $i));

            if ($i % 100 !== 0) {
                continue;
            }

            $profile->reborn();
        }

        $this->repository->save($profile);
    }
}
