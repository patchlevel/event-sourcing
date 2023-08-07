<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\EventBus\Decorator\ChainMessageDecorator;
use Patchlevel\EventSourcing\EventBus\Decorator\SplitStreamDecorator;
use Patchlevel\EventSourcing\EventBus\DefaultEventBus;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AttributeAggregateRootRegistryFactory;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Projection\Projection\Store\InMemoryStore;
use Patchlevel\EventSourcing\Projection\Projectionist\DefaultProjectionist;
use Patchlevel\EventSourcing\Projection\Projectionist\SyncProjectionistEventBusWrapper;
use Patchlevel\EventSourcing\Projection\Projector\InMemoryProjectorRepository;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\DoctrineDbalStore;
use Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream\Aggregate\BankAccount;
use Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream\Events\BalanceAdded;
use Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream\Events\BankAccountCreated;
use Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream\Events\MonthPassed;
use Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream\Projection\BankAccountProjection;
use Patchlevel\EventSourcing\Tests\Integration\DbalManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore as LockInMemoryStore;

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

    public function testSuccessful(): void
    {
        $store = new DoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            (new AttributeAggregateRootRegistryFactory())->create([__DIR__ . '/Aggregate']),
            'eventstore',
        );

        $bankAccountProjection = new BankAccountProjection($this->connection);
        $projectionRepository = new InMemoryProjectorRepository([$bankAccountProjection]);

        $projectionist = new DefaultProjectionist(
            $store,
            new InMemoryStore(),
            $projectionRepository,
        );

        $eventStream = new SyncProjectionistEventBusWrapper(
            new DefaultEventBus(),
            $projectionist,
            new LockFactory(
                new LockInMemoryStore(),
            ),
        );

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['bank_account' => BankAccount::class]),
            $store,
            $eventStream,
            null,
            new ChainMessageDecorator([
                new SplitStreamDecorator(new AttributeEventMetadataFactory()),
            ]),
        );
        $repository = $manager->get(BankAccount::class);

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();
        $projectionist->boot();

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
