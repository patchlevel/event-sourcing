<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream;

use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Event\AttributeEventRegistryFactory;
use Patchlevel\EventSourcing\Repository\AggregateOutdated;
use Patchlevel\EventSourcing\Repository\DefaultRepositoryManager;
use Patchlevel\EventSourcing\Repository\MessageDecorator\SplitStreamDecorator;
use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Repository\StoreAdapter\TagStoreAdapter;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Serializer\DefaultEventSerializer;
use Patchlevel\EventSourcing\Store\TaggableDoctrineDbalStore;
use Patchlevel\EventSourcing\Tests\DbalManager;
use Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream\Events\BalanceAdded;
use Patchlevel\EventSourcing\Tests\Integration\BankAccountSplitStream\Events\MonthPassed;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class TagStoreAdapterTest extends TestCase
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

    public function testSaveAndLoad(): void
    {
        $repository = $this->repository();

        $bankAccountId = AccountId::generate();
        $bankAccount = BankAccount::create($bankAccountId, 'John');
        $bankAccount->addBalance(100);
        $repository->save($bankAccount);

        $repository = $this->repository();
        $bankAccount = $repository->load($bankAccountId);
        $bankAccount->addBalance(500);
        $repository->save($bankAccount);

        $bankAccount = $this->repository()->load($bankAccountId);

        self::assertSame(3, $bankAccount->playhead());
        self::assertSame(600, $bankAccount->balance());

        $rows = $this->connection->fetchAllAssociative('SELECT stream, playhead, tags FROM event_store ORDER BY id');

        self::assertCount(3, $rows);

        foreach ($rows as $row) {
            self::assertSame('main', $row['stream']);
            self::assertNull($row['playhead']);
            self::assertIsString($row['tags']);
            self::assertStringContainsString('profile:' . $bankAccountId->toString(), $row['tags']);
        }
    }

    public function testSplitStream(): void
    {
        $repository = $this->repository();

        $bankAccountId = AccountId::generate();
        $bankAccount = BankAccount::create($bankAccountId, 'John');
        $bankAccount->addBalance(100);
        $bankAccount->addBalance(500);
        $repository->save($bankAccount);

        $bankAccount->beginNewMonth();
        $bankAccount->addBalance(200);
        $repository->save($bankAccount);

        $bankAccount = $this->repository()->load($bankAccountId);

        self::assertSame(5, $bankAccount->playhead());
        self::assertSame(800, $bankAccount->balance());
        self::assertCount(2, $bankAccount->appliedEvents);
        self::assertInstanceOf(MonthPassed::class, $bankAccount->appliedEvents[0]);
        self::assertInstanceOf(BalanceAdded::class, $bankAccount->appliedEvents[1]);
    }

    public function testConcurrentSave(): void
    {
        $bankAccountId = AccountId::generate();
        $this->repository()->save(BankAccount::create($bankAccountId, 'John'));

        $repositoryA = $this->repository();
        $repositoryB = $this->repository();

        $bankAccountA = $repositoryA->load($bankAccountId);
        $bankAccountB = $repositoryB->load($bankAccountId);

        $bankAccountA->addBalance(100);
        $repositoryA->save($bankAccountA);

        $bankAccountB->addBalance(200);

        $this->expectException(AggregateOutdated::class);

        $repositoryB->save($bankAccountB);
    }

    /** @return Repository<BankAccount> */
    private function repository(): Repository
    {
        $store = new TaggableDoctrineDbalStore(
            $this->connection,
            DefaultEventSerializer::createFromPaths([__DIR__ . '/Events']),
            (new AttributeEventRegistryFactory())->create([__DIR__ . '/Events']),
        );

        if (!$this->connection->createSchemaManager()->tablesExist(['event_store'])) {
            (new DoctrineSchemaDirector($this->connection, $store))->create();
        }

        $manager = new DefaultRepositoryManager(
            new AggregateRootRegistry(['profile' => BankAccount::class]),
            new TagStoreAdapter($store),
            null,
            null,
            new SplitStreamDecorator(new AttributeEventMetadataFactory()),
        );

        return $manager->get(BankAccount::class);
    }
}
