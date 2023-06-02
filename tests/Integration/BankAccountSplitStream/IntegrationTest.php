<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Clock\SystemClock;
use Patchlevel\EventSourcing\EventBus\Decorator\ChainMessageDecorator;
use Patchlevel\EventSourcing\EventBus\Decorator\RecordedOnDecorator;
use Patchlevel\EventSourcing\EventBus\Decorator\SplitStreamDecorator;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AttributeAggregateRootRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Projection\Projector\InMemoryProjectorRepository;
use Patchlevel\EventSourcing\Projection\Projector\SyncProjectorListener;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\MultiTableStore;
use Patchlevel\EventSourcing\Store\SingleTableStore;
use Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream\Aggregate\BankAccount;
use Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream\Events\BalanceAdded;
use Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream\Events\BankAccountCreated;
use Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream\Events\MonthPassed;
use Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream\Projection\BankAccountProjection;
use Patchlevel\EventSourcing\Tests\Integration\DbalManager;
use PHPUnit\Framework\TestCase;

use function count;

/** @coversNothing */
final class IntegrationTest extends TestCase
{
    private Connection $connection;

    public function setUp(): void
    {
        $this->connection = DbalManager::createConnection();
    }

    public function tearDown(): void
    {
        $this->connection->close();
    }

    public function testSingleTableSuccessful(): void
    {
        $bankAccountProjection = new BankAccountProjection($this->connection);
        $projectionRepository = new InMemoryProjectorRepository([$bankAccountProjection]);

        $eventStream = new DefaultEventBus();
        $eventStream->addListener(new SyncProjectorListener($projectionRepository));

        $store = new SingleTableStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            (new AttributeAggregateRootRegistryFactory())->create([__DIR__ . '/Aggregate']),
            'eventstore',
        );

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['bank_account' => BankAccount::class]),
            $store,
            $eventStream,
            null,
            new ChainMessageDecorator([
                new RecordedOnDecorator(new SystemClock()),
                new SplitStreamDecorator(new AttributeEventMetadataFactory()),
            ]),
        );
        $repository = $manager->get(BankAccount::class);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();
        $bankAccountProjection->create();

        $bankAccount = BankAccount::create(AccountId::fromString('1'), 'John');
        $bankAccount->addBalance(100);
        $bankAccount->addBalance(500);
        $repository->save($bankAccount);

        $result = $this->connection->fetchAssociative('SELECT * FROM projection_bank_account WHERE id = ?', ['1']);

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertSame('1', $result['id']);
        self::assertSame('John', $result['name']);
        self::assertSame(600, $result['balance_in_cents']);

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['bank_account' => BankAccount::class]),
            $store,
            $eventStream,
            null,
            new ChainMessageDecorator([
                new RecordedOnDecorator(new SystemClock()),
                new SplitStreamDecorator(new AttributeEventMetadataFactory()),
            ]),
        );
        $repository = $manager->get(BankAccount::class);
        $bankAccount = $repository->load('1');

        self::assertInstanceOf(BankAccount::class, $bankAccount);
        self::assertSame('1', $bankAccount->aggregateRootId());
        self::assertSame(3, $bankAccount->playhead());
        self::assertSame('John', $bankAccount->name());
        self::assertSame(600, $bankAccount->balance());
        self::assertSame(3, count($bankAccount->appliedEvents));
        self::assertInstanceOf(BankAccountCreated::class, $bankAccount->appliedEvents[0]);
        self::assertInstanceOf(BalanceAdded::class, $bankAccount->appliedEvents[1]);
        self::assertInstanceOf(BalanceAdded::class, $bankAccount->appliedEvents[2]);

        $bankAccount->beginNewMonth();
        $bankAccount->addBalance(200);
        $repository->save($bankAccount);

        $result = $this->connection->fetchAssociative('SELECT * FROM projection_bank_account WHERE id = ?', ['1']);

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertSame('1', $result['id']);
        self::assertSame('John', $result['name']);
        self::assertSame(800, $result['balance_in_cents']);

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['bank_account' => BankAccount::class]),
            $store,
            $eventStream,
            null,
            new ChainMessageDecorator([
                new RecordedOnDecorator(new SystemClock()),
                new SplitStreamDecorator(new AttributeEventMetadataFactory()),
            ]),
        );
        $repository = $manager->get(BankAccount::class);
        $bankAccount = $repository->load('1');

        self::assertInstanceOf(BankAccount::class, $bankAccount);
        self::assertSame('1', $bankAccount->aggregateRootId());
        self::assertSame(5, $bankAccount->playhead());
        self::assertSame('John', $bankAccount->name());
        self::assertSame(800, $bankAccount->balance());
        self::assertSame(2, count($bankAccount->appliedEvents));
        self::assertInstanceOf(MonthPassed::class, $bankAccount->appliedEvents[0]);
        self::assertInstanceOf(BalanceAdded::class, $bankAccount->appliedEvents[1]);
    }

    public function testMultiTableSuccessful(): void
    {
        $bankAccountProjection = new BankAccountProjection($this->connection);
        $projectionRepository = new InMemoryProjectorRepository([$bankAccountProjection]);

        $eventStream = new DefaultEventBus();
        $eventStream->addListener(new SyncProjectorListener($projectionRepository));

        $store = new MultiTableStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            (new AttributeAggregateRootRegistryFactory())->create([__DIR__ . '/Aggregate']),
        );

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['bank_account' => BankAccount::class]),
            $store,
            $eventStream,
            null,
            new ChainMessageDecorator([
                new RecordedOnDecorator(new SystemClock()),
                new SplitStreamDecorator(new AttributeEventMetadataFactory()),
            ]),
        );
        $repository = $manager->get(BankAccount::class);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();
        $bankAccountProjection->create();

        $bankAccount = BankAccount::create(AccountId::fromString('1'), 'John');
        $bankAccount->addBalance(100);
        $bankAccount->addBalance(500);
        $repository->save($bankAccount);

        $result = $this->connection->fetchAssociative('SELECT * FROM projection_bank_account WHERE id = ?', ['1']);

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertSame('1', $result['id']);
        self::assertSame('John', $result['name']);
        self::assertSame(600, $result['balance_in_cents']);

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['bank_account' => BankAccount::class]),
            $store,
            $eventStream,
            null,
            new ChainMessageDecorator([
                new RecordedOnDecorator(new SystemClock()),
                new SplitStreamDecorator(new AttributeEventMetadataFactory()),
            ]),
        );
        $repository = $manager->get(BankAccount::class);
        $bankAccount = $repository->load('1');

        self::assertInstanceOf(BankAccount::class, $bankAccount);
        self::assertSame('1', $bankAccount->aggregateRootId());
        self::assertSame(3, $bankAccount->playhead());
        self::assertSame('John', $bankAccount->name());
        self::assertSame(600, $bankAccount->balance());
        self::assertSame(3, count($bankAccount->appliedEvents));
        self::assertInstanceOf(BankAccountCreated::class, $bankAccount->appliedEvents[0]);
        self::assertInstanceOf(BalanceAdded::class, $bankAccount->appliedEvents[1]);
        self::assertInstanceOf(BalanceAdded::class, $bankAccount->appliedEvents[2]);

        $bankAccount->beginNewMonth();
        $bankAccount->addBalance(200);
        $repository->save($bankAccount);

        $result = $this->connection->fetchAssociative('SELECT * FROM projection_bank_account WHERE id = ?', ['1']);

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertSame('1', $result['id']);
        self::assertSame('John', $result['name']);
        self::assertSame(800, $result['balance_in_cents']);

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['bank_account' => BankAccount::class]),
            $store,
            $eventStream,
            null,
            new ChainMessageDecorator([
                new RecordedOnDecorator(new SystemClock()),
                new SplitStreamDecorator(new AttributeEventMetadataFactory()),
            ]),
        );
        $repository = $manager->get(BankAccount::class);
        $bankAccount = $repository->load('1');

        self::assertInstanceOf(BankAccount::class, $bankAccount);
        self::assertSame('1', $bankAccount->aggregateRootId());
        self::assertSame(5, $bankAccount->playhead());
        self::assertSame('John', $bankAccount->name());
        self::assertSame(800, $bankAccount->balance());
        self::assertSame(2, count($bankAccount->appliedEvents));
        self::assertInstanceOf(MonthPassed::class, $bankAccount->appliedEvents[0]);
        self::assertInstanceOf(BalanceAdded::class, $bankAccount->appliedEvents[1]);
    }
}
