<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BasicImplementation;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\CommandBus\CommandBus;
use Patchlevel\EventSourcing\Container\Configuration;
use Patchlevel\EventSourcing\Container\Factory;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Message\Pipe;
use Patchlevel\EventSourcing\Message\Reducer;
use Patchlevel\EventSourcing\Message\Translator\UntilEventTranslator;
use Patchlevel\EventSourcing\QueryBus\QueryBus;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Patchlevel\EventSourcing\Schema\SchemaDirector;
use Patchlevel\EventSourcing\Snapshot\Adapter\InMemorySnapshotAdapter;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\StreamCriterion;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Tests\DbalManager;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Command\AdjustStockForProduct;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Command\ChangeProfileName;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Command\CreateProfile;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Command\DecreaseStockForProduct;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Events\NameChanged;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Events\ProfileCreated;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\MessageDecorator\FooMessageDecorator;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Processor\SendEmailProcessor;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Projection\ProfileProjector;
use Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Query\QueryProfileName;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

use function sprintf;

#[CoversNothing]
final class BasicIntegrationTest extends TestCase
{
    private Connection $connection;

    public function setUp(): void
    {
        $this->connection = DbalManager::createConnection();
    }

    public function tearDown(): void
    {
        $this->connection->close();
        SendEmailMock::reset();
    }

    public function testSuccessful(): void
    {
        $profileProjector = new ProfileProjector($this->connection);

        $configuration = $this->getConfiguration()
            ->withSubscribers([$profileProjector, new SendEmailProcessor()])
            ->withMessageDecorators([new FooMessageDecorator()]);
        $container = Factory::create($configuration);

        $manager = $container->get(RepositoryManager::class);
        $engine = $container->get(SubscriptionEngine::class);

        $repository = $manager->get(Profile::class);

        $schemaDirector = $container->get(SchemaDirector::class);
        $schemaDirector->create();

        $engine->setup(skipBooting: true);

        $profileId = ProfileId::generate();
        $profile = Profile::create($profileId, 'John');
        $repository->save($profile);

        $result = $this->connection->fetchAssociative(
            'SELECT * FROM projection_profile WHERE id = ?',
            [$profileId->toString()],
        );

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertSame($profileId->toString(), $result['id']);
        self::assertSame('John', $result['name']);

        $repository = $manager->get(Profile::class);
        $profile = $repository->load($profileId);

        self::assertInstanceOf(Profile::class, $profile);
        self::assertEquals($profileId, $profile->aggregateRootId());
        self::assertSame(1, $profile->playhead());
        self::assertSame('John', $profile->name());
        self::assertSame(1, SendEmailMock::count());
    }

    public function testSnapshot(): void
    {
        $profileProjection = new ProfileProjector($this->connection);
        $configuration = $this->getConfiguration()
            ->withSubscribers([$profileProjection, new SendEmailProcessor()])
            ->withMessageDecorators([new FooMessageDecorator()])
            ->withSnapshotAdapters(['default' => new InMemorySnapshotAdapter()]);
        $container = Factory::create($configuration);

        $manager = $container->get(RepositoryManager::class);
        $engine = $container->get(SubscriptionEngine::class);
        $schemaDirector = $container->get(SchemaDirector::class);

        $repository = $manager->get(Profile::class);

        $schemaDirector->create();
        $engine->setup(skipBooting: true);

        $profileId = ProfileId::generate();
        $profile = Profile::create($profileId, 'John');
        $repository->save($profile);

        $result = $this->connection->fetchAssociative(
            'SELECT * FROM projection_profile WHERE id = ?',
            [$profileId->toString()],
        );

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertSame($profileId->toString(), $result['id']);
        self::assertSame('John', $result['name']);

        $repository = $manager->get(Profile::class);
        $profile = $repository->load($profileId);

        self::assertInstanceOf(Profile::class, $profile);
        self::assertEquals($profileId, $profile->aggregateRootId());
        self::assertSame(1, $profile->playhead());
        self::assertSame('John', $profile->name());
        self::assertSame(1, SendEmailMock::count());
    }

    public function testTempProjection(): void
    {
        $configuration = $this->getConfiguration();
        $container = Factory::create($configuration);

        $manager = $container->get(RepositoryManager::class);
        $store = $container->get(Store::class);
        $repository = $manager->get(Profile::class);

        $schemaDirector = $container->get(SchemaDirector::class);
        $schemaDirector->create();

        $profileId = ProfileId::generate();
        $profile = Profile::create($profileId, 'John');

        for ($i = 0; $i < 100; $i++) {
            $profile->changeName('John' . $i);
        }

        $repository->save($profile);

        $state = (new Reducer())
            ->initState(['name' => 'unknown'])
            ->match([
                ProfileCreated::class => static function (Message $message): array {
                    return ['name' => $message->event()->name];
                },
                NameChanged::class => static function (Message $message): array {
                    return ['name' => $message->event()->name];
                },
            ])
            ->reduce(
                new Pipe(
                    $store->load(new Criteria(
                        new StreamCriterion(sprintf('profile-%s', $profileId->toString())),
                    )),
                    new UntilEventTranslator(new DateTimeImmutable()),
                ),
            );

        self::assertSame(['name' => 'John99'], $state);
    }

    public function testCommandBus(): void
    {
        $profileProjection = new ProfileProjector($this->connection);
        $configuration = $this->getConfiguration()
            ->withSubscribers([$profileProjection, new SendEmailProcessor()])
            ->withParameter('env', 'test');
        $container = Factory::create($configuration);

        $manager = $container->get(RepositoryManager::class);
        $engine = $container->get(SubscriptionEngine::class);
        $commandBus = $container->get(CommandBus::class);

        $schemaDirector = $container->get(SchemaDirector::class);
        $schemaDirector->create();
        $engine->setup(skipBooting: true);

        $profileId = ProfileId::generate();

        $commandBus->dispatch(new CreateProfile($profileId, 'John'));
        $commandBus->dispatch(new ChangeProfileName($profileId, 'John Doe'));

        $result = $this->connection->fetchAssociative(
            'SELECT * FROM projection_profile WHERE id = ?',
            [$profileId->toString()],
        );

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertSame($profileId->toString(), $result['id']);
        self::assertSame('John Doe', $result['name']);

        $repository = $manager->get(ProfileWithCommands::class);
        $profile = $repository->load($profileId);

        self::assertInstanceOf(ProfileWithCommands::class, $profile);
        self::assertEquals($profileId, $profile->aggregateRootId());
        self::assertSame(2, $profile->playhead());
        self::assertSame('John Doe', $profile->name());
        self::assertSame(1, SendEmailMock::count());
    }

    public function testQueryBus(): void
    {
        $profileProjection = new ProfileProjector($this->connection);
        $configuration = $this->getConfiguration()
            ->withSubscribers([$profileProjection])
            ->withParameter('env', 'test');
        $container = Factory::create($configuration);

        $engine = $container->get(SubscriptionEngine::class);
        $commandBus = $container->get(CommandBus::class);
        $queryBus = $container->get(QueryBus::class);
        $schemaDirector = $container->get(SchemaDirector::class);

        $schemaDirector->create();
        $engine->setup(skipBooting: true);

        $profileId = ProfileId::generate();

        $commandBus->dispatch(new CreateProfile($profileId, 'John'));
        $commandBus->dispatch(new ChangeProfileName($profileId, 'John Doe'));

        $result = $queryBus->dispatch(new QueryProfileName($profileId));

        self::assertSame('John Doe', $result);
    }

    public function testAggregateInitialization(): void
    {
        $configuration = $this->getConfiguration();
        $container = Factory::create($configuration);

        $manager = $container->get(RepositoryManager::class);
        $commandBus = $container->get(CommandBus::class);
        $schemaDirector = $container->get(SchemaDirector::class);

        $schemaDirector->create();

        $stockId = StockId::create();
        $productId = ProductId::generate();

        $commandBus->dispatch(new AdjustStockForProduct($stockId, $productId, 5));
        $commandBus->dispatch(new DecreaseStockForProduct($stockId, $productId, 3));

        $repository = $manager->get(Stock::class);
        $stock = $repository->load($stockId);

        self::assertEquals($stockId, $stock->aggregateRootId());
        self::assertSame(3, $stock->playhead());
        self::assertSame(2, $stock->stockFor($productId));
    }

    public function getConfiguration(): Configuration
    {
        return Configuration::createWithConnectionService($this->connection)
            ->withDefaultSettings(
                [__DIR__],
                [__DIR__ . '/Events'],
            )
            ->withHeaders(__DIR__ . '/Header')
            ->withSubscriptionInMemoryStore()
            ->withSubscriptionEngineThrowOnError()
            ->withRunSubscriptionsAfterAggregateSave();
    }
}
