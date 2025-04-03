<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Repository\MessageDecorator\ChainMessageDecorator;
use Patchlevel\EventSourcing\Repository\MessageDecorator\SplitStreamDecorator;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\ArchivedHeader;
use Patchlevel\EventSourcing\Store\DoctrineDbalStore;
use Patchlevel\EventSourcing\Store\StreamDoctrineDbalStore;
use Patchlevel\EventSourcing\Store\StreamStartHeader;
use Patchlevel\EventSourcing\Subscription\Engine\DefaultSubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Store\InMemorySubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Tests\DbalManager;
use Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream\Events\BalanceAdded;
use Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream\Events\BankAccountCreated;
use Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream\Events\MonthPassed;
use Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream\Projection\BankAccountProjector;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

use function count;
use function iterator_to_array;

#[CoversNothing]
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
        );

        $bankAccountProjector = new BankAccountProjector($this->connection);

        $engine = new DefaultSubscriptionEngine(
            $store,
            new InMemorySubscriptionStore(),
            new MetadataSubscriberAccessorRepository([$bankAccountProjector]),
        );

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['bank_account' => BankAccount::class]),
            $store,
            null,
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
        $engine->setup();
        $engine->boot();

        $bankAccountId = AccountId::generate();
        $bankAccount = BankAccount::create($bankAccountId, 'John');
        $bankAccount->addBalance(100);
        $bankAccount->addBalance(500);
        $repository->save($bankAccount);

        $engine->run();

        $result = $this->connection->fetchAssociative(
            'SELECT * FROM projection_bank_account WHERE id = ?',
            [$bankAccountId->toString()],
        );

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertSame($bankAccountId->toString(), $result['id']);
        self::assertSame('John', $result['name']);
        self::assertSame(600, $result['balance_in_cents']);

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['bank_account' => BankAccount::class]),
            $store,
            null,
            null,
            new ChainMessageDecorator([
                new SplitStreamDecorator(new AttributeEventMetadataFactory()),
            ]),
        );
        $repository = $manager->get(BankAccount::class);
        $bankAccount = $repository->load($bankAccountId);

        self::assertInstanceOf(BankAccount::class, $bankAccount);
        self::assertEquals($bankAccountId, $bankAccount->aggregateRootId());
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

        $engine->run();

        $result = $this->connection->fetchAssociative(
            'SELECT * FROM projection_bank_account WHERE id = ?',
            [$bankAccountId->toString()],
        );

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertSame($bankAccountId->toString(), $result['id']);
        self::assertSame('John', $result['name']);
        self::assertSame(800, $result['balance_in_cents']);

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['bank_account' => BankAccount::class]),
            $store,
            null,
            null,
            new ChainMessageDecorator([
                new SplitStreamDecorator(new AttributeEventMetadataFactory()),
            ]),
        );
        $repository = $manager->get(BankAccount::class);
        $bankAccount = $repository->load($bankAccountId);

        self::assertInstanceOf(BankAccount::class, $bankAccount);
        self::assertEquals($bankAccountId, $bankAccount->aggregateRootId());
        self::assertSame(5, $bankAccount->playhead());
        self::assertSame('John', $bankAccount->name());
        self::assertSame(800, $bankAccount->balance());
        self::assertSame(2, count($bankAccount->appliedEvents));
        self::assertInstanceOf(MonthPassed::class, $bankAccount->appliedEvents[0]);
        self::assertInstanceOf(BalanceAdded::class, $bankAccount->appliedEvents[1]);
    }

    public function testSuccessfulWithStreamStore(): void
    {
        $store = new StreamDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
        );

        $bankAccountProjector = new BankAccountProjector($this->connection);

        $engine = new DefaultSubscriptionEngine(
            $store,
            new InMemorySubscriptionStore(),
            new MetadataSubscriberAccessorRepository([$bankAccountProjector]),
        );

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['bank_account' => BankAccount::class]),
            $store,
            null,
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
        $engine->setup();
        $engine->boot();

        $bankAccountId = AccountId::generate();
        $bankAccount = BankAccount::create($bankAccountId, 'John');
        $bankAccount->addBalance(100);
        $bankAccount->addBalance(500);
        $repository->save($bankAccount);

        $engine->run();

        $result = $this->connection->fetchAssociative(
            'SELECT * FROM projection_bank_account WHERE id = ?',
            [$bankAccountId->toString()],
        );

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertSame($bankAccountId->toString(), $result['id']);
        self::assertSame('John', $result['name']);
        self::assertSame(600, $result['balance_in_cents']);

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['bank_account' => BankAccount::class]),
            $store,
            null,
            null,
            new ChainMessageDecorator([
                new SplitStreamDecorator(new AttributeEventMetadataFactory()),
            ]),
        );

        $repository = $manager->get(BankAccount::class);
        $bankAccount = $repository->load($bankAccountId);

        self::assertInstanceOf(BankAccount::class, $bankAccount);
        self::assertEquals($bankAccountId, $bankAccount->aggregateRootId());
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

        $engine->run();

        $result = $this->connection->fetchAssociative(
            'SELECT * FROM projection_bank_account WHERE id = ?',
            [$bankAccountId->toString()],
        );

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertSame($bankAccountId->toString(), $result['id']);
        self::assertSame('John', $result['name']);
        self::assertSame(800, $result['balance_in_cents']);

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['bank_account' => BankAccount::class]),
            $store,
            null,
            null,
            new ChainMessageDecorator([
                new SplitStreamDecorator(new AttributeEventMetadataFactory()),
            ]),
        );
        $repository = $manager->get(BankAccount::class);
        $bankAccount = $repository->load($bankAccountId);

        self::assertInstanceOf(BankAccount::class, $bankAccount);
        self::assertEquals($bankAccountId, $bankAccount->aggregateRootId());
        self::assertSame(5, $bankAccount->playhead());
        self::assertSame('John', $bankAccount->name());
        self::assertSame(800, $bankAccount->balance());
        self::assertSame(2, count($bankAccount->appliedEvents));
        self::assertInstanceOf(MonthPassed::class, $bankAccount->appliedEvents[0]);
        self::assertInstanceOf(BalanceAdded::class, $bankAccount->appliedEvents[1]);

        /** @var list<Message> $messages */
        $messages = iterator_to_array($store->load());

        self::assertCount(5, $messages);

        self::assertTrue($messages[0]->hasHeader(ArchivedHeader::class));
        self::assertTrue($messages[1]->hasHeader(ArchivedHeader::class));
        self::assertTrue($messages[2]->hasHeader(ArchivedHeader::class));

        self::assertTrue($messages[3]->hasHeader(StreamStartHeader::class));

        self::assertFalse($messages[3]->hasHeader(ArchivedHeader::class));
        self::assertFalse($messages[4]->hasHeader(ArchivedHeader::class));
    }

    public function testRemoveArchived(): void
    {
        $store = new DoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
        );

        $bankAccountProjector = new BankAccountProjector($this->connection);

        $engine = new DefaultSubscriptionEngine(
            $store,
            new InMemorySubscriptionStore(),
            new MetadataSubscriberAccessorRepository([$bankAccountProjector]),
        );

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['bank_account' => BankAccount::class]),
            $store,
            null,
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
        $engine->setup();
        $engine->boot();

        $bankAccountId = AccountId::generate();
        $bankAccount = BankAccount::create($bankAccountId, 'John');
        $bankAccount->addBalance(100);
        $bankAccount->addBalance(500);
        $repository->save($bankAccount);

        $engine->run();

        $result = $this->connection->fetchAssociative(
            'SELECT * FROM projection_bank_account WHERE id = ?',
            [$bankAccountId->toString()],
        );

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertSame($bankAccountId->toString(), $result['id']);
        self::assertSame('John', $result['name']);
        self::assertSame(600, $result['balance_in_cents']);

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['bank_account' => BankAccount::class]),
            $store,
            null,
            null,
            new ChainMessageDecorator([
                new SplitStreamDecorator(new AttributeEventMetadataFactory()),
            ]),
        );
        $repository = $manager->get(BankAccount::class);
        $bankAccount = $repository->load($bankAccountId);

        self::assertInstanceOf(BankAccount::class, $bankAccount);
        self::assertEquals($bankAccountId, $bankAccount->aggregateRootId());
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

        $engine->run();

        $result = $this->connection->fetchAssociative(
            'SELECT * FROM projection_bank_account WHERE id = ?',
            [$bankAccountId->toString()],
        );

        self::assertIsArray($result);
        self::assertArrayHasKey('id', $result);
        self::assertSame($bankAccountId->toString(), $result['id']);
        self::assertSame('John', $result['name']);
        self::assertSame(800, $result['balance_in_cents']);

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['bank_account' => BankAccount::class]),
            $store,
            null,
            null,
            new ChainMessageDecorator([
                new SplitStreamDecorator(new AttributeEventMetadataFactory()),
            ]),
        );
        $repository = $manager->get(BankAccount::class);
        $bankAccount = $repository->load($bankAccountId);

        self::assertInstanceOf(BankAccount::class, $bankAccount);
        self::assertEquals($bankAccountId, $bankAccount->aggregateRootId());
        self::assertSame(5, $bankAccount->playhead());
        self::assertSame('John', $bankAccount->name());
        self::assertSame(800, $bankAccount->balance());
        self::assertSame(2, count($bankAccount->appliedEvents));
        self::assertInstanceOf(MonthPassed::class, $bankAccount->appliedEvents[0]);
        self::assertInstanceOf(BalanceAdded::class, $bankAccount->appliedEvents[1]);
    }
}
