<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark;

use Patchlevel\EventSourcing\Aggregate\AggregateRootId;
use Patchlevel\EventSourcing\Cryptography\DefaultEventPayloadCryptographer;
use Patchlevel\EventSourcing\Cryptography\Store\DoctrineCipherKeyStore;
use Patchlevel\EventSourcing\Message\Serializer\DefaultHeadersSerializer;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Repository\DefaultRepository;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Schema\ChainDoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\DoctrineDbalStore;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Profile;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\ProfileId;
use Patchlevel\EventSourcing\Tests\DbalManager;
use PhpBench\Attributes as Bench;

#[Bench\BeforeMethods('setUp')]
final class PersonalDataBench
{
    private Store $store;
    private Repository $repository;

    private AggregateRootId $singleEventId;
    private AggregateRootId $multipleEventsId;

    public function setUp(): void
    {
        $connection = DbalManager::createConnection();

        $cipherKeyStore = new DoctrineCipherKeyStore($connection);

        $cryptographer = DefaultEventPayloadCryptographer::createWithOpenssl(
            new AttributeEventMetadataFactory(),
            $cipherKeyStore,
        );

        $this->store = new DoctrineDbalStore(
            $connection,
            DefaultEventSerializer::createFromPaths(
                [__DIR__ . '/BasicImplementation/Events'],
                cryptographer: $cryptographer,
            ),
            DefaultHeadersSerializer::createFromPaths([
                __DIR__ . '/../../src',
                __DIR__ . '/BasicImplementation/Events',
            ]),
            'eventstore',
        );

        $this->repository = new DefaultRepository($this->store, Profile::metadata());

        $schemaDirector = new DoctrineSchemaDirector(
            $connection,
            new ChainDoctrineSchemaConfigurator([
                $this->store,
                $cipherKeyStore,
            ]),
        );

        $schemaDirector->create();
        $schemaDirector->create();

        $this->singleEventId = ProfileId::v7();
        $profile = Profile::create($this->singleEventId, 'Peter');
        $this->repository->save($profile);

        $this->multipleEventsId = ProfileId::v7();
        $profile = Profile::create($this->multipleEventsId, 'Peter', 'info@patchlevel.de');

        for ($i = 0; $i < 10_000; $i++) {
            $profile->changeEmail('info@patchlevel.de');
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
        $profile = Profile::create(ProfileId::v7(), 'Peter', 'info@patchlevel.de');
        $this->repository->save($profile);
    }

    #[Bench\Revs(10)]
    public function benchSave10000Events(): void
    {
        $profile = Profile::create(ProfileId::v7(), 'Peter', 'info@patchlevel.de');

        for ($i = 1; $i < 10_000; $i++) {
            $profile->changeEmail('info@patchlevel.de');
        }

        $this->repository->save($profile);
    }

    #[Bench\Revs(1)]
    public function benchSave10000Aggregates(): void
    {
        for ($i = 1; $i < 10_000; $i++) {
            $profile = Profile::create(ProfileId::v7(), 'Peter', 'info@patchlevel.de');
            $this->repository->save($profile);
        }
    }

    #[Bench\Revs(10)]
    public function benchSave10000AggregatesTransaction(): void
    {
        $this->store->transactional(function (): void {
            for ($i = 1; $i < 10_000; $i++) {
                $profile = Profile::create(ProfileId::v7(), 'Peter', 'info@patchlevel.de');
                $this->repository->save($profile);
            }
        });
    }
}
