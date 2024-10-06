<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark;

use Patchlevel\EventSourcing\Aggregate\AggregateRootId;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Repository\DefaultRepository;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Schema\ChainDoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\DoctrineDbalStore;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Subscription\Engine\DefaultSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Store\DoctrineSubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Processor\SendEmailProcessor;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Profile;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\ProfileId;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Projection\ProfileProjector;
use Patchlevel\EventSourcing\Tests\DbalManager;
use PhpBench\Attributes as Bench;

#[Bench\BeforeMethods('setUp')]
final class SubscriptionEngineBench
{
    private Store $store;
    private Repository $repository;

    private SubscriptionEngine $subscriptionEngine;

    private AggregateRootId $id;

    public function setUp(): void
    {
        $connection = DbalManager::createConnection();

        $this->store = new DoctrineDbalStore(
            $connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/BasicImplementation/Events']),
        );

        $this->repository = new DefaultRepository($this->store, Profile::metadata());

        $subscriptionStore = new DoctrineSubscriptionStore(
            $connection,
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $connection,
            new ChainDoctrineSchemaConfigurator([
                $this->store,
                $subscriptionStore,
            ]),
        );

        $schemaDirector->create();

        $this->id = ProfileId::generate();

        $profile = Profile::create($this->id, 'Peter');

        for ($i = 1; $i < 10_000; $i++) {
            $profile->changeEmail('peter' . $i . '@example.com');
            $profile->changeName('Peter ' . $i);
        }

        $this->repository->save($profile);

        $this->subscriptionEngine = new DefaultSubscriptionEngine(
            $this->store,
            $subscriptionStore,
            new MetadataSubscriberAccessorRepository(
                [
                    new ProfileProjector($connection),
                    new SendEmailProcessor(),
                ],
            ),
            eventMetadataFactory: new AttributeEventMetadataFactory(),
        );
    }

    #[Bench\Revs(10)]
    public function benchHandle10000Events(): void
    {
        $this->subscriptionEngine->setup();
        $this->subscriptionEngine->boot();
        $this->subscriptionEngine->remove();
    }
}
