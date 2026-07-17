<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Store;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Exception;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Patchlevel\EventSourcing\Subscription\Cleanup\Dbal\DropTableTask;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\DoctrineSubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionNotFound;
use Patchlevel\EventSourcing\Subscription\Store\TransactionCommitNotPossible;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Subscription\SubscriptionError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

use function serialize;

#[CoversClass(DoctrineSubscriptionStore::class)]
final class DoctrineSubscriptionStoreTest extends TestCase
{
    public function testGet(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->atLeastOnce())
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturnCallback(
                static fn (): QueryBuilder => new QueryBuilder($connection),
            );
        $connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->with('SELECT * FROM subscriptions WHERE id = :id', ['id' => 'foo'])
            ->willReturn([
                'id' => 'foo',
                'group_name' => 'default',
                'run_mode' => 'from_beginning',
                'position' => 42,
                'status' => 'active',
                'error_message' => null,
                'error_previous_status' => null,
                'error_context' => null,
                'retry_attempt' => 0,
                'last_saved_at' => '2024-01-01 10:00:00',
                'cleanup_tasks' => null,
            ]);

        $store = new DoctrineSubscriptionStore($connection);

        self::assertEquals(
            new Subscription(
                'foo',
                'default',
                RunMode::FromBeginning,
                Status::Active,
                42,
                null,
                0,
                new DateTimeImmutable('2024-01-01 10:00:00'),
            ),
            $store->get('foo'),
        );
    }

    public function testGetWithErrorAndCleanupTasks(): void
    {
        $task = new DropTableTask('projection_table');

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->atLeastOnce())
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturnCallback(
                static fn (): QueryBuilder => new QueryBuilder($connection),
            );
        $connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->with('SELECT * FROM subscriptions WHERE id = :id', ['id' => 'foo'])
            ->willReturn([
                'id' => 'foo',
                'group_name' => 'default',
                'run_mode' => 'from_beginning',
                'position' => null,
                'status' => 'error',
                'error_message' => 'something went wrong',
                'error_previous_status' => 'active',
                'error_context' => '[]',
                'retry_attempt' => 2,
                'last_saved_at' => '2024-01-01 10:00:00',
                'cleanup_tasks' => serialize([$task]),
            ]);

        $store = new DoctrineSubscriptionStore($connection);

        self::assertEquals(
            new Subscription(
                'foo',
                'default',
                RunMode::FromBeginning,
                Status::Error,
                null,
                new SubscriptionError('something went wrong', Status::Active, []),
                2,
                new DateTimeImmutable('2024-01-01 10:00:00'),
                [$task],
            ),
            $store->get('foo'),
        );
    }

    public function testGetNotFound(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->atLeastOnce())
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturnCallback(
                static fn (): QueryBuilder => new QueryBuilder($connection),
            );
        $connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->with('SELECT * FROM subscriptions WHERE id = :id', ['id' => 'foo'])
            ->willReturn(false);

        $store = new DoctrineSubscriptionStore($connection);

        $this->expectException(SubscriptionNotFound::class);

        $store->get('foo');
    }

    public function testFind(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->atLeastOnce())
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturnCallback(
                static fn (): QueryBuilder => new QueryBuilder($connection),
            );
        $result = $this->createMock(Result::class);
        $result
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([
                [
                    'id' => 'foo',
                    'group_name' => 'default',
                    'run_mode' => 'from_beginning',
                    'position' => null,
                    'status' => 'new',
                    'error_message' => null,
                    'error_previous_status' => null,
                    'error_context' => null,
                    'retry_attempt' => 0,
                    'last_saved_at' => '2024-01-01 10:00:00',
                    'cleanup_tasks' => null,
                ],
            ]);
        $connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT * FROM subscriptions ORDER BY id', [], $this->isArray())
            ->willReturn($result);

        $store = new DoctrineSubscriptionStore($connection);

        $subscriptions = $store->find();

        self::assertCount(1, $subscriptions);
        self::assertSame('foo', $subscriptions[0]->id());
    }

    public function testFindWithCriteria(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->atLeastOnce())
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturnCallback(
                static fn (): QueryBuilder => new QueryBuilder($connection),
            );
        $result = $this->createMock(Result::class);
        $result
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([]);
        $connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with(
                'SELECT * FROM subscriptions WHERE (id IN (:ids)) AND (group_name IN (:groups)) AND (status IN (:status)) ORDER BY id',
                [
                    'ids' => ['foo'],
                    'groups' => ['default'],
                    'status' => ['active'],
                ],
                $this->isArray(),
            )
            ->willReturn($result);

        $store = new DoctrineSubscriptionStore($connection);

        self::assertSame([], $store->find(new SubscriptionCriteria(['foo'], ['default'], [Status::Active])));
    }

    public function testClaim(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->atLeastOnce())
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturnCallback(
                static fn (): QueryBuilder => new QueryBuilder($connection),
            );
        $result = $this->createMock(Result::class);
        $result
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn([
                'id' => 'foo',
                'group_name' => 'default',
                'run_mode' => 'from_beginning',
                'position' => null,
                'status' => 'active',
                'error_message' => null,
                'error_previous_status' => null,
                'error_context' => null,
                'retry_attempt' => 0,
                'last_saved_at' => '2024-01-01 10:00:00',
                'cleanup_tasks' => null,
            ]);
        $connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with(
                'SELECT * FROM subscriptions WHERE (id = :id) AND (status IN (:status))',
                [
                    'id' => 'foo',
                    'status' => ['active'],
                ],
                $this->isArray(),
            )
            ->willReturn($result);

        $store = new DoctrineSubscriptionStore($connection);

        $subscription = $store->claim('foo', new SubscriptionCriteria(status: [Status::Active]));

        self::assertInstanceOf(Subscription::class, $subscription);
        self::assertSame('foo', $subscription->id());
    }

    public function testClaimNotFound(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->atLeastOnce())
            ->method('getDatabasePlatform')
            ->willReturn(new SQLitePlatform());
        $connection
            ->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturnCallback(
                static fn (): QueryBuilder => new QueryBuilder($connection),
            );
        $result = $this->createMock(Result::class);
        $result
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(false);
        $connection
            ->expects($this->once())
            ->method('executeQuery')
            ->willReturn($result);

        $store = new DoctrineSubscriptionStore($connection);

        self::assertNull($store->claim('foo', new SubscriptionCriteria(status: [Status::Active])));
    }

    public function testAdd(): void
    {
        $now = new DateTimeImmutable('2024-01-01 10:00:00');

        $clock = $this->createMock(ClockInterface::class);
        $clock
            ->expects($this->once())
            ->method('now')
            ->willReturn($now);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('insert')
            ->with(
                'subscriptions',
                [
                    'id' => 'foo',
                    'group_name' => 'default',
                    'run_mode' => 'from_beginning',
                    'status' => 'new',
                    'position' => null,
                    'error_message' => null,
                    'error_previous_status' => null,
                    'error_context' => null,
                    'retry_attempt' => 0,
                    'last_saved_at' => $now,
                    'cleanup_tasks' => null,
                ],
                ['last_saved_at' => Types::DATETIME_IMMUTABLE],
            );

        $store = new DoctrineSubscriptionStore($connection, $clock);

        $store->add(new Subscription('foo'));
    }

    public function testAddWithError(): void
    {
        $now = new DateTimeImmutable('2024-01-01 10:00:00');

        $clock = $this->createMock(ClockInterface::class);
        $clock
            ->expects($this->once())
            ->method('now')
            ->willReturn($now);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('insert')
            ->with(
                'subscriptions',
                [
                    'id' => 'foo',
                    'group_name' => 'default',
                    'run_mode' => 'from_beginning',
                    'status' => 'error',
                    'position' => null,
                    'error_message' => 'something went wrong',
                    'error_previous_status' => 'active',
                    'error_context' => '[]',
                    'retry_attempt' => 0,
                    'last_saved_at' => $now,
                    'cleanup_tasks' => null,
                ],
                ['last_saved_at' => Types::DATETIME_IMMUTABLE],
            );

        $store = new DoctrineSubscriptionStore($connection, $clock);

        $store->add(new Subscription(
            'foo',
            status: Status::Error,
            error: new SubscriptionError('something went wrong', Status::Active, []),
        ));
    }

    public function testUpdate(): void
    {
        $now = new DateTimeImmutable('2024-01-01 10:00:00');

        $clock = $this->createMock(ClockInterface::class);
        $clock
            ->expects($this->once())
            ->method('now')
            ->willReturn($now);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('update')
            ->with(
                'subscriptions',
                [
                    'group_name' => 'default',
                    'run_mode' => 'from_beginning',
                    'status' => 'new',
                    'position' => null,
                    'error_message' => null,
                    'error_previous_status' => null,
                    'error_context' => null,
                    'retry_attempt' => 0,
                    'last_saved_at' => $now,
                    'cleanup_tasks' => null,
                ],
                ['id' => 'foo'],
                ['last_saved_at' => Types::DATETIME_IMMUTABLE],
            )
            ->willReturn(1);

        $store = new DoctrineSubscriptionStore($connection, $clock);

        $store->update(new Subscription('foo'));
    }

    public function testUpdateNotFound(): void
    {
        $now = new DateTimeImmutable('2024-01-01 10:00:00');

        $clock = $this->createMock(ClockInterface::class);
        $clock
            ->expects($this->once())
            ->method('now')
            ->willReturn($now);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('update')
            ->willReturn(0);

        $store = new DoctrineSubscriptionStore($connection, $clock);

        $this->expectException(SubscriptionNotFound::class);

        $store->update(new Subscription('foo'));
    }

    public function testRemove(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('delete')
            ->with('subscriptions', ['id' => 'foo']);

        $store = new DoctrineSubscriptionStore($connection);

        $store->remove(new Subscription('foo'));
    }

    public function testInLock(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('beginTransaction');
        $connection
            ->expects($this->once())
            ->method('commit');

        $store = new DoctrineSubscriptionStore($connection);

        $value = new DateTimeImmutable();

        self::assertSame($value, $store->inLock(static fn (): DateTimeImmutable => $value));
    }

    public function testInLockRetryableException(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('beginTransaction');
        $connection
            ->expects($this->once())
            ->method('commit');

        $store = new DoctrineSubscriptionStore($connection);

        $this->expectException(TransactionCommitNotPossible::class);

        $store->inLock(static function (): void {
            throw new DeadlockException(new Exception('deadlock'), null);
        });
    }

    public function testInLockCommitNotPossible(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('beginTransaction');
        $connection
            ->expects($this->once())
            ->method('commit')
            ->willThrowException(new DriverException(new Exception('commit failed'), null));

        $store = new DoctrineSubscriptionStore($connection);

        $this->expectException(TransactionCommitNotPossible::class);

        $store->inLock(static fn (): string => 'result');
    }

    public function testConfigureSchema(): void
    {
        $connection = $this->createMock(Connection::class);

        $store = new DoctrineSubscriptionStore($connection);

        $expectedSchema = new Schema();
        $table = $expectedSchema->createTable('subscriptions');
        $table->addColumn('id', Types::STRING)
            ->setLength(255)
            ->setNotnull(true);
        $table->addColumn('group_name', Types::STRING)
            ->setLength(32)
            ->setNotnull(true);
        $table->addColumn('run_mode', Types::STRING)
            ->setLength(16)
            ->setNotnull(true);
        $table->addColumn('position', Types::INTEGER)
            ->setNotnull(false);
        $table->addColumn('status', Types::STRING)
            ->setLength(32)
            ->setNotnull(true);
        $table->addColumn('error_message', Types::TEXT)
            ->setNotnull(false);
        $table->addColumn('error_previous_status', Types::STRING)
            ->setLength(32)
            ->setNotnull(false);
        $table->addColumn('error_context', Types::JSON)
            ->setNotnull(false);
        $table->addColumn('retry_attempt', Types::INTEGER)
            ->setNotnull(true);
        $table->addColumn('last_saved_at', Types::DATETIMETZ_IMMUTABLE)
            ->setNotnull(true);
        $table->addColumn('cleanup_tasks', Types::TEXT)
            ->setNotnull(false);

        $table->setPrimaryKey(['id']);
        $table->addIndex(['group_name']);
        $table->addIndex(['status']);

        $schema = new Schema();
        $store->configureSchema($schema, $connection);

        self::assertEquals($expectedSchema, $schema);
    }

    public function testConfigureSchemaWithDifferentDatabase(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('getParams')
            ->willReturn(['dbname' => 'db']);

        $differentConnection = $this->createMock(Connection::class);
        $differentConnection
            ->expects($this->once())
            ->method('getParams')
            ->willReturn(['dbname' => 'db2']);

        $store = new DoctrineSubscriptionStore($connection);

        $schema = new Schema();
        $store->configureSchema($schema, $differentConnection);

        self::assertEquals(new Schema(), $schema);
    }
}
