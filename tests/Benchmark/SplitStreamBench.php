<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark;

use Patchlevel\EventSourcing\Aggregate\AggregateRootId;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Repository\DefaultRepository;
use Patchlevel\EventSourcing\Repository\MessageDecorator\SplitStreamDecorator;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\DoctrineDbalStore;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Aggregate\Profile;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\ProfileId;
use Patchlevel\EventSourcing\Tests\DbalManager;
use PhpBench\Attributes as Bench;

use function sprintf;

#[Bench\BeforeMethods('setUp')]
final class SplitStreamBench
{
    private Store $store;
    private Repository $repository;

    private AggregateRootId $id;

    public function setUp(): void
    {
        $connection = DbalManager::createConnection();

        $this->store = new DoctrineDbalStore(
            $connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/BasicImplementation/Events']),
        );

        $this->repository = new DefaultRepository(
            $this->store,
            Profile::metadata(),
            null,
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

    #[Bench\Revs(10)]
    #[Bench\BeforeMethods('provideData')]
    public function benchLoad10000Events(): void
    {
        $this->repository->load($this->id);
    }

    #[Bench\Revs(10)]
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
