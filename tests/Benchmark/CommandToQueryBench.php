<?php

declare(strict_types=1);

namespace Benchmark;

use Patchlevel\EventSourcing\Clock\SystemClock;
use Patchlevel\EventSourcing\CommandBus\CommandBus;
use Patchlevel\EventSourcing\CommandBus\ServiceLocator;
use Patchlevel\EventSourcing\CommandBus\SyncCommandBus;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\QueryBus\QueryBus;
use Patchlevel\EventSourcing\QueryBus\ServiceHandlerProvider;
use Patchlevel\EventSourcing\QueryBus\SyncQueryBus;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Repository\StoreAdapter\StreamDoctrineDbalStoreAdapter;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Snapshot\Adapter\InMemorySnapshotAdapter;
use Patchlevel\EventSourcing\Snapshot\DefaultSnapshotStore;
use Patchlevel\EventSourcing\Store\StreamDoctrineDbalStore;
use Patchlevel\EventSourcing\Subscription\Engine\DefaultSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\StoreMessageLoader;
use Patchlevel\EventSourcing\Subscription\Repository\RunSubscriptionEngineRepositoryManager;
use Patchlevel\EventSourcing\Subscription\Store\InMemorySubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Command\ChangeProfileName;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Command\CreateProfile;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Processor\SendEmailProcessor;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\ProfileId;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\ProfileWithCommands;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Projection\ProfileProjector;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Query\QueryProfileName;
use Patchlevel\EventSourcing\Tests\DbalManager;
use PhpBench\Attributes as Bench;
use Psr\Clock\ClockInterface;

use function assert;

#[Bench\BeforeMethods('setUp')]
final class CommandToQueryBench
{
    private CommandBus $commandBus;
    private QueryBus $queryBus;

    private ProfileId $updateId;

    public function setUp(): void
    {
        $connection = DbalManager::createConnection();
        $store = new StreamDoctrineDbalStore(
            $connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/BasicImplementation/Events']),
        );

        $aggregateRootRegistry = new AggregateRootRegistry(['profile_with_commands' => ProfileWithCommands::class]);

        $manager = new DefaultRepositoryManager(
            $aggregateRootRegistry,
            new StreamDoctrineDbalStoreAdapter($store),
            null,
            new DefaultSnapshotStore(['default' => new InMemorySnapshotAdapter()]),
        );

        $projectionConnection = DbalManager::createConnection();
        $profileProjection = new ProfileProjector($projectionConnection);

        $engine = new DefaultSubscriptionEngine(
            new StoreMessageLoader($store),
            new InMemorySubscriptionStore(),
            new MetadataSubscriberAccessorRepository([
                $profileProjection,
                new SendEmailProcessor(),
            ]),
        );

        $manager = new RunSubscriptionEngineRepositoryManager($manager, $engine);

        $this->commandBus = SyncCommandBus::createForAggregateHandlers(
            $aggregateRootRegistry,
            $manager,
            new ServiceLocator([
                ClockInterface::class => new SystemClock(),
                'env' => 'test',
            ]),
        );

        $this->queryBus = new SyncQueryBus(new ServiceHandlerProvider([$profileProjection]));

        $schemaDirector = new DoctrineSchemaDirector($connection, $store);

        $schemaDirector->create();
        $engine->setup(skipBooting: true);

        $this->updateId = ProfileId::generate();
        $this->commandBus->dispatch(new CreateProfile($this->updateId, 'Peter'));
    }

    #[Bench\Revs(10)]
    public function benchCreate(): void
    {
        $id = ProfileId::generate();
        $this->commandBus->dispatch(new CreateProfile($id, 'James'));
        $result = $this->queryBus->dispatch(new QueryProfileName($id));

        assert($result === 'James');
    }

    #[Bench\Revs(10)]
    public function benchUpdate(): void
    {
        $this->commandBus->dispatch(new ChangeProfileName($this->updateId, 'James Doe'));
        $result = $this->queryBus->dispatch(new QueryProfileName($this->updateId));

        assert($result === 'James Doe');
    }

    #[Bench\Revs(10)]
    public function benchBoth(): void
    {
        $id = ProfileId::generate();
        $this->commandBus->dispatch(new CreateProfile($id, 'James'));
        $result = $this->queryBus->dispatch(new QueryProfileName($id));
        assert($result === 'James');

        $this->commandBus->dispatch(new ChangeProfileName($id, 'James Doe'));
        $result = $this->queryBus->dispatch(new QueryProfileName($id));
        assert($result === 'James Doe');
    }
}
