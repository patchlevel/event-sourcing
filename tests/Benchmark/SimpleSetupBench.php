<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark;

use Patchlevel\EventSourcing\Aggregate\AggregateRootId;
use Patchlevel\EventSourcing\Repository\DefaultRepository;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\DoctrineDbalStore;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Profile;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\ProfileId;
use Patchlevel\EventSourcing\Tests\DbalManager;
use PhpBench\Attributes as Bench;

#[Bench\BeforeMethods('setUp')]
final class SimpleSetupBench
{
    private Store $store;
    private Repository $repository;

    private AggregateRootId $singleEventId;
    private AggregateRootId $multipleEventsId;

    public function setUp(): void
    {
        $connection = DbalManager::createConnection();

        $this->store = new DoctrineDbalStore(
            $connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/BasicImplementation/Events']),
        );

        $this->repository = new DefaultRepository($this->store, Profile::metadata());

        $schemaDirector = new DoctrineSchemaDirector(
            $connection,
            $this->store,
        );

        $schemaDirector->create();

        $this->singleEventId = ProfileId::generate();
        $profile = Profile::create($this->singleEventId, 'Peter');
        $this->repository->save($profile);

        $this->multipleEventsId = ProfileId::generate();
        $profile = Profile::create($this->multipleEventsId, 'Peter');

        for ($i = 0; $i < 10_000; $i++) {
            $profile->changeName('Peter');
        }

        $this->repository->save($profile);
    }

    #[Bench\Revs(10)]
    public function benchLoad1Event(): void
    {
        $this->repository->load($this->singleEventId);
    }

    #[Bench\Revs(10)]
    public function benchLoad10000Events(): void
    {
        $this->repository->load($this->multipleEventsId);
    }

    #[Bench\Revs(10)]
    public function benchSave1Event(): void
    {
        $profile = Profile::create(ProfileId::generate(), 'Peter');
        $this->repository->save($profile);
    }

    #[Bench\Revs(10)]
    public function benchSave10000Events(): void
    {
        $profile = Profile::create(ProfileId::generate(), 'Peter');

        for ($i = 1; $i < 10_000; $i++) {
            $profile->changeName('Peter');
        }

        $this->repository->save($profile);
    }

    #[Bench\Revs(1)]
    public function benchSave10000Aggregates(): void
    {
        for ($i = 1; $i < 10_000; $i++) {
            $profile = Profile::create(ProfileId::generate(), 'Peter');
            $this->repository->save($profile);
        }
    }

    #[Bench\Revs(10)]
    public function benchSave10000AggregatesTransaction(): void
    {
        $this->store->transactional(function (): void {
            for ($i = 1; $i < 10_000; $i++) {
                $profile = Profile::create(ProfileId::generate(), 'Peter');
                $this->repository->save($profile);
            }
        });
    }
}
