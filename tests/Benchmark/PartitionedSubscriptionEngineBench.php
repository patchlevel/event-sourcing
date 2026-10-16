<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Benchmark;

use Generator;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Schema\ChainDoctrineSchemaConfigurator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\Header\PlayheadHeader;
use Patchlevel\EventSourcing\Store\Header\StreamNameHeader;
use Patchlevel\EventSourcing\Store\StreamDoctrineDbalStore;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Run;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Setup;
use Patchlevel\EventSourcing\Subscription\Engine\DefaultSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\EventFilteredStoreMessageLoader;
use Patchlevel\EventSourcing\Subscription\Engine\ProcessedResult;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\ThrowOnErrorSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Store\DoctrineSubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events\NameChanged;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Events\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\ProfileId;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Projection\CountingProjector;
use Patchlevel\EventSourcing\Tests\Benchmark\BasicImplementation\Projection\PartitionedCountingProjector;
use Patchlevel\EventSourcing\Tests\DbalManager;
use PhpBench\Attributes as Bench;
use RuntimeException;

use function array_chunk;
use function array_map;
use function array_slice;
use function explode;
use function getenv;
use function is_string;

/**
 * One partition per aggregate. Every aggregate gets a created event first and a name change after all other
 * aggregates were created, so the two events of a partition are as far apart in the stream as possible.
 *
 * The number of aggregates can be set with BENCH_PARTITION_AGGREGATES, e.g. "1000,10000".
 */
#[Bench\ParamProviders('provideAggregates')]
final class PartitionedSubscriptionEngineBench
{
    private const INCREMENTAL_EVENTS = 10;

    private StreamDoctrineDbalStore $store;
    private SubscriptionEngine $engine;
    private PartitionedCountingProjector|CountingProjector $projector;

    /** @var list<ProfileId> */
    private array $profileIds = [];

    /** @return Generator<string, array{aggregates: int}> */
    public function provideAggregates(): Generator
    {
        $aggregates = getenv('BENCH_PARTITION_AGGREGATES');

        foreach (explode(',', is_string($aggregates) ? $aggregates : '1000') as $count) {
            yield $count . ' aggregates' => ['aggregates' => (int)$count];
        }
    }

    /** @param array{aggregates: int} $params */
    public function setUpPartitioned(array $params): void
    {
        $this->setUp($params['aggregates'], new PartitionedCountingProjector());
    }

    /** @param array{aggregates: int} $params */
    public function setUpPlain(array $params): void
    {
        $this->setUp($params['aggregates'], new CountingProjector());
    }

    /** @param array{aggregates: int} $params */
    public function setUpPartitionedCaughtUp(array $params): void
    {
        $this->setUpPartitioned($params);
        $this->catchUp($params['aggregates'] * 2);
        $this->appendNameChanges();
    }

    /** @param array{aggregates: int} $params */
    public function setUpPlainCaughtUp(array $params): void
    {
        $this->setUpPlain($params);
        $this->catchUp($params['aggregates'] * 2);
        $this->appendNameChanges();
    }

    /**
     * Processes every event from the beginning, so the partitions are created on the way.
     *
     * @param array{aggregates: int} $params
     */
    #[Bench\BeforeMethods('setUpPartitioned')]
    #[Bench\Revs(1)]
    #[Bench\Iterations(1)]
    public function benchCatchUpPartitioned(array $params): void
    {
        $this->catchUp($params['aggregates'] * 2);
    }

    /** @param array{aggregates: int} $params */
    #[Bench\BeforeMethods('setUpPlain')]
    #[Bench\Revs(1)]
    #[Bench\Iterations(1)]
    public function benchCatchUpPlain(array $params): void
    {
        $this->catchUp($params['aggregates'] * 2);
    }

    /**
     * Everything is processed, then a few aggregates get new events: the cost of the daily business.
     *
     * @param array{aggregates: int} $params
     */
    #[Bench\BeforeMethods('setUpPartitionedCaughtUp')]
    #[Bench\Revs(1)]
    #[Bench\Iterations(3)]
    public function benchIncrementalPartitioned(array $params): void
    {
        $this->catchUp($params['aggregates'] * 2 + self::INCREMENTAL_EVENTS);
    }

    /** @param array{aggregates: int} $params */
    #[Bench\BeforeMethods('setUpPlainCaughtUp')]
    #[Bench\Revs(1)]
    #[Bench\Iterations(3)]
    public function benchIncrementalPlain(array $params): void
    {
        $this->catchUp($params['aggregates'] * 2 + self::INCREMENTAL_EVENTS);
    }

    private function setUp(int $aggregates, PartitionedCountingProjector|CountingProjector $projector): void
    {
        $connection = DbalManager::createConnection();

        $this->store = new StreamDoctrineDbalStore(
            $connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/BasicImplementation/Events']),
        );

        $subscriptionStore = new DoctrineSubscriptionStore($connection);

        (new DoctrineSchemaDirector(
            $connection,
            new ChainDoctrineSchemaConfigurator([$this->store, $subscriptionStore]),
        ))->create();

        $this->profileIds = [];

        for ($i = 0; $i < $aggregates; $i++) {
            $this->profileIds[] = ProfileId::generate();
        }

        $this->save(array_map(
            static fn (ProfileId $id): Message => self::message(new ProfileCreated($id, 'Peter', null), $id, 1),
            $this->profileIds,
        ));

        $this->save(array_map(
            static fn (ProfileId $id): Message => self::message(new NameChanged($id, 'Paul'), $id, 2),
            $this->profileIds,
        ));

        $this->projector = $projector;
        $subscriberRepository = new MetadataSubscriberAccessorRepository([$projector]);

        $this->engine = new ThrowOnErrorSubscriptionEngine(new DefaultSubscriptionEngine(
            new EventFilteredStoreMessageLoader($this->store, new AttributeEventMetadataFactory(), $subscriberRepository),
            $subscriptionStore,
            $subscriberRepository,
        ));

        $this->engine->execute(new Setup(skipBooting: true));
    }

    private function appendNameChanges(): void
    {
        $this->save(array_map(
            static fn (ProfileId $id): Message => self::message(new NameChanged($id, 'Mary'), $id, 3),
            array_slice($this->profileIds, 0, self::INCREMENTAL_EVENTS),
        ));
    }

    /** Runs until all events are handled, like a worker would. */
    private function catchUp(int $expectedHandled): void
    {
        for ($i = 0; $i < 10_000; $i++) {
            $result = $this->engine->execute(new Run());

            if ($result instanceof ProcessedResult && $result->finished) {
                break;
            }
        }

        if ($this->projector->handled !== $expectedHandled) {
            throw new RuntimeException('Expected ' . $expectedHandled . ' handled events, got ' . $this->projector->handled);
        }
    }

    /** @param list<Message> $messages */
    private function save(array $messages): void
    {
        foreach (array_chunk($messages, 1000) as $chunk) {
            $this->store->save(...$chunk);
        }
    }

    /** @param positive-int $playhead */
    private static function message(object $event, ProfileId $id, int $playhead): Message
    {
        return Message::create($event)
            ->withHeader(new StreamNameHeader('profile-' . $id->toString()))
            ->withHeader(new PlayheadHeader($playhead));
    }
}
