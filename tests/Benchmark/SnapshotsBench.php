<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark;

use Patchlevel\EventSourcing\Aggregate\AggregateRootId;
use Patchlevel\EventSourcing\Message\Serializer\DefaultHeadersSerializer;
use Patchlevel\EventSourcing\Repository\DefaultRepository;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Snapshot\Adapter\InMemorySnapshotAdapter;
use Patchlevel\EventSourcing\Snapshot\DefaultSnapshotStore;
use Patchlevel\EventSourcing\Snapshot\SnapshotStore;
use Patchlevel\EventSourcing\Store\DoctrineDbalStore;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Aggregate\Profile;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\ProfileId;
use Patchlevel\EventSourcing\Tests\DbalManager;
use PhpBench\Attributes as Bench;

#[Bench\BeforeMethods('setUp')]
final class SnapshotsBench
{
    private Store $store;
    private SnapshotStore $snapshotStore;
    private Repository $repository;

    private InMemorySnapshotAdapter $adapter;

    private AggregateRootId $id;

    public function setUp(): void
    {
        $connection = DbalManager::createConnection();

        $this->store = new DoctrineDbalStore(
            $connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/BasicImplementation/Events']),
            DefaultHeadersSerializer::createFromPaths([
                __DIR__ . '/../../src',
                __DIR__ . '/BasicImplementation/Events',
            ]),
            'eventstore',
        );

        $this->adapter = new InMemorySnapshotAdapter();

        $this->snapshotStore = new DefaultSnapshotStore(['default' => $this->adapter]);

        $this->repository = new DefaultRepository($this->store, Profile::metadata(), null, $this->snapshotStore);

        $schemaDirector = new DoctrineSchemaDirector(
            $connection,
            $this->store,
        );

        $schemaDirector->create();

        $this->id = ProfileId::v7();
        $profile = Profile::create($this->id, 'Peter');

        for ($i = 0; $i < 10_000; $i++) {
            $profile->changeName('Peter');
        }

        $this->repository->save($profile);
        $this->snapshotStore->save($profile);
    }

    #[Bench\Revs(10)]
    public function benchLoad10000EventsMissingSnapshot(): void
    {
        $this->adapter->clear();
        $this->repository->load($this->id);
    }

    #[Bench\Revs(10)]
    public function benchLoad10000Events(): void
    {
        $this->repository->load($this->id);
    }
}
