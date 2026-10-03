<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Schema\Schema;
use Generator;
use LogicException;
use Patchlevel\EventSourcing\Clock\FrozenClock;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Subscription\Cleanup\Dbal\DropIndexTask;
use Patchlevel\EventSourcing\Subscription\Cleanup\Dbal\DropTableTask;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\DoctrineSubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionAlreadyExists;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionNotFound;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Tests\DbalManager;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function array_map;
use function str_repeat;

#[CoversNothing]
final class DoctrineSubscriptionStoreTest extends TestCase
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

    public function testUpdate(): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->add(new Subscription('foo'));

        $subscription = $store->get('foo');
        $subscription->active();
        $subscription->changePosition(42);

        $store->update($subscription);

        $loaded = $store->get('foo');

        self::assertSame(Status::Active, $loaded->status());
        self::assertSame(42, $loaded->position());
    }

    public function testUpdateWithoutChanges(): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->add(new Subscription('foo'));

        // the clock is frozen, so all values stay the same and mysql reports no affected rows
        $store->update($store->get('foo'));
        $store->update($store->get('foo'));

        self::assertSame('foo', $store->get('foo')->id());
    }

    public function testUpdateUnknownSubscription(): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $this->expectException(SubscriptionNotFound::class);

        $store->update(new Subscription('foo'));
    }

    public function testAddAndGet(): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->add(new Subscription(
            'foo',
            'bar',
            RunMode::FromNow,
            Status::Active,
            10,
            null,
            2,
            null,
            [new DropTableTask('baz')],
        ));

        $loaded = $store->get('foo');

        self::assertSame('foo', $loaded->id());
        self::assertSame('bar', $loaded->group());
        self::assertSame(RunMode::FromNow, $loaded->runMode());
        self::assertSame(Status::Active, $loaded->status());
        self::assertSame(10, $loaded->position());
        self::assertNull($loaded->subscriptionError());
        self::assertSame(2, $loaded->retryAttempt());
        self::assertEquals(new DateTimeImmutable('2021-01-01T00:00:00'), $loaded->lastSavedAt());
        self::assertEquals([new DropTableTask('baz')], $loaded->cleanupTasks());
    }

    public function testAddDuplicateSubscription(): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->add(new Subscription('foo', position: 42));

        $exception = null;

        try {
            $store->add(new Subscription('foo'));
        } catch (SubscriptionAlreadyExists $e) {
            $exception = $e;
        }

        self::assertInstanceOf(SubscriptionAlreadyExists::class, $exception);
        self::assertInstanceOf(UniqueConstraintViolationException::class, $exception->getPrevious());
        self::assertSame(42, $store->get('foo')->position());
    }

    public function testGetUnknownSubscription(): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $this->expectException(SubscriptionNotFound::class);

        $store->get('foo');
    }

    public function testUpdateAllFields(): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->add(new Subscription('foo'));

        $subscription = $store->get('foo');
        $subscription->changeGroup('bar');
        $subscription->changeRunMode(RunMode::Once);
        $subscription->changePosition(42);
        $subscription->replaceCleanupTasks([new DropTableTask('baz')]);

        $store->update($subscription);

        $loaded = $store->get('foo');

        self::assertSame('bar', $loaded->group());
        self::assertSame(RunMode::Once, $loaded->runMode());
        self::assertSame(42, $loaded->position());
        self::assertEquals([new DropTableTask('baz')], $loaded->cleanupTasks());
    }

    public function testUpdateWithError(): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->add(new Subscription('foo', status: Status::Active));

        $subscription = $store->get('foo');
        $subscription->error(new RuntimeException('error message'));

        $store->update($subscription);

        $loaded = $store->get('foo');

        self::assertSame(Status::Error, $loaded->status());

        $error = $loaded->subscriptionError();

        self::assertNotNull($error);
        self::assertSame('error message', $error->errorMessage);
        self::assertSame(Status::Active, $error->previousStatus);
        self::assertNotNull($error->errorContext);
        self::assertSame(RuntimeException::class, $error->errorContext[0]['class']);
        self::assertSame('error message', $error->errorContext[0]['message']);
    }

    public function testUpdateWithFailedFromString(): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->add(new Subscription('foo', status: Status::Active));

        $subscription = $store->get('foo');
        $subscription->failed('error message');

        $store->update($subscription);

        $loaded = $store->get('foo');

        self::assertSame(Status::Failed, $loaded->status());

        $error = $loaded->subscriptionError();

        self::assertNotNull($error);
        self::assertSame('error message', $error->errorMessage);
        self::assertSame(Status::Active, $error->previousStatus);
        self::assertNull($error->errorContext);
    }

    public function testUpdateRefreshesLastSavedAt(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00'));

        $store = new DoctrineSubscriptionStore(
            $this->connection,
            $clock,
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->add(new Subscription('foo'));

        $clock->update(new DateTimeImmutable('2021-01-02T12:30:00'));

        $subscription = $store->get('foo');
        $store->update($subscription);

        self::assertEquals(new DateTimeImmutable('2021-01-02T12:30:00'), $subscription->lastSavedAt());
        self::assertEquals(new DateTimeImmutable('2021-01-02T12:30:00'), $store->get('foo')->lastSavedAt());
    }

    public function testUpdateAfterRetry(): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->add(new Subscription('foo', status: Status::Active));

        $subscription = $store->get('foo');
        $subscription->error('error message');
        $store->update($subscription);

        $subscription = $store->get('foo');
        $subscription->doRetry();
        $store->update($subscription);

        $loaded = $store->get('foo');

        self::assertSame(Status::Active, $loaded->status());
        self::assertNull($loaded->subscriptionError());
        self::assertSame(1, $loaded->retryAttempt());
    }

    public function testUpdateResetCleanupTasks(): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->add(new Subscription('foo', cleanupTasks: [new DropTableTask('baz')]));

        $subscription = $store->get('foo');
        $subscription->replaceCleanupTasks(null);

        $store->update($subscription);

        self::assertNull($store->get('foo')->cleanupTasks());
    }

    public function testUpdateWithChangesAfterUpdateWithoutChanges(): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->add(new Subscription('foo'));

        $store->update($store->get('foo'));

        $subscription = $store->get('foo');
        $subscription->changePosition(42);
        $store->update($subscription);

        self::assertSame(42, $store->get('foo')->position());
    }

    public function testUpdateDoesNotAffectOtherSubscriptions(): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->add(new Subscription('foo'));
        $store->add(new Subscription('bar'));

        $subscription = $store->get('foo');
        $subscription->changePosition(42);
        $store->update($subscription);

        self::assertSame(42, $store->get('foo')->position());
        self::assertNull($store->get('bar')->position());
    }

    public function testUpdateRemovedSubscription(): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->add(new Subscription('foo'));

        $subscription = $store->get('foo');
        $store->remove($subscription);

        $this->expectException(SubscriptionNotFound::class);

        $store->update($subscription);
    }

    public function testRemove(): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->add(new Subscription('foo'));
        $store->add(new Subscription('bar'));

        $store->remove($store->get('foo'));

        self::assertSame(['bar'], $this->ids($store->find()));
    }

    public function testRemoveUnknownSubscription(): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->add(new Subscription('foo'));

        $store->remove(new Subscription('bar'));

        self::assertSame(['foo'], $this->ids($store->find()));
    }

    public function testFindWithoutCriteria(): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->add(new Subscription('c'));
        $store->add(new Subscription('a'));
        $store->add(new Subscription('b'));

        self::assertSame(['a', 'b', 'c'], $this->ids($store->find()));
    }

    public function testFindEmpty(): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        self::assertSame([], $store->find());
    }

    public function testFindByIds(): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->add(new Subscription('a'));
        $store->add(new Subscription('b'));
        $store->add(new Subscription('c'));

        self::assertSame(
            ['a', 'c'],
            $this->ids($store->find(new SubscriptionCriteria(ids: ['a', 'c', 'unknown']))),
        );
    }

    public function testFindByGroups(): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->add(new Subscription('a', 'foo'));
        $store->add(new Subscription('b', 'bar'));
        $store->add(new Subscription('c', 'baz'));

        self::assertSame(
            ['a', 'b'],
            $this->ids($store->find(new SubscriptionCriteria(groups: ['foo', 'bar']))),
        );
    }

    public function testFindByStatus(): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->add(new Subscription('a', status: Status::Active));
        $store->add(new Subscription('b', status: Status::Error));
        $store->add(new Subscription('c', status: Status::New));

        self::assertSame(
            ['a', 'b'],
            $this->ids($store->find(new SubscriptionCriteria(status: [Status::Active, Status::Error]))),
        );
    }

    public function testFindByCombinedCriteria(): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->add(new Subscription('a', 'foo', status: Status::Active));
        $store->add(new Subscription('b', 'foo', status: Status::New));
        $store->add(new Subscription('c', 'bar', status: Status::Active));
        $store->add(new Subscription('d', 'foo', status: Status::Active));

        self::assertSame(
            ['a'],
            $this->ids($store->find(new SubscriptionCriteria(
                ids: ['a', 'b', 'c'],
                groups: ['foo'],
                status: [Status::Active],
            ))),
        );
    }

    public function testFindWithEmptyCriteriaLists(): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->add(new Subscription('a'));

        self::assertSame([], $store->find(new SubscriptionCriteria(ids: [])));
        self::assertSame([], $store->find(new SubscriptionCriteria(groups: [])));
        self::assertSame([], $store->find(new SubscriptionCriteria(status: [])));
    }

    public function testInLock(): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->add(new Subscription('foo'));

        $result = $store->inLock(static function () use ($store): string {
            $subscription = $store->find(new SubscriptionCriteria(ids: ['foo']))[0];
            $subscription->changePosition(42);
            $store->update($subscription);

            return $subscription->id();
        });

        self::assertSame('foo', $result);
        self::assertSame(42, $store->get('foo')->position());
        self::assertFalse($this->connection->isTransactionActive());
    }

    public function testInLockWithUpdateWithoutChanges(): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->add(new Subscription('foo'));

        $store->inLock(static function () use ($store): void {
            foreach ($store->find() as $subscription) {
                $store->update($subscription);
            }
        });

        self::assertFalse($this->connection->isTransactionActive());
        self::assertSame('foo', $store->get('foo')->id());
    }

    public function testInLockCommitsWhenClosureThrows(): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->add(new Subscription('foo'));

        $exception = null;

        try {
            $store->inLock(static function () use ($store): void {
                $subscription = $store->get('foo');
                $subscription->changePosition(42);
                $store->update($subscription);

                throw new RuntimeException('error');
            });
        } catch (RuntimeException $e) {
            $exception = $e;
        }

        self::assertSame('error', $exception->getMessage());
        self::assertFalse($this->connection->isTransactionActive());
        self::assertSame(42, $store->get('foo')->position());
    }

    public function testCustomTableName(): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
            'custom_subscriptions',
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $schemaManager = $this->connection->createSchemaManager();

        self::assertTrue($schemaManager->tablesExist(['custom_subscriptions']));
        self::assertFalse($schemaManager->tablesExist(['subscriptions']));

        $store->add(new Subscription('foo'));

        $subscription = $store->get('foo');
        $subscription->changePosition(42);
        $store->update($subscription);

        self::assertSame(42, $store->get('foo')->position());
    }

    public function testAddSetsLastSavedAt(): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $subscription = new Subscription('foo');

        $store->add($subscription);

        self::assertEquals(new DateTimeImmutable('2021-01-01T00:00:00'), $subscription->lastSavedAt());
    }

    #[DataProvider('statusProvider')]
    public function testStatusRoundTrip(Status $status): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->add(new Subscription('foo', status: $status));

        self::assertSame($status, $store->get('foo')->status());
        self::assertSame(['foo'], $this->ids($store->find(new SubscriptionCriteria(status: [$status]))));
    }

    public static function statusProvider(): Generator
    {
        foreach (Status::cases() as $status) {
            yield $status->value => [$status];
        }
    }

    #[DataProvider('runModeProvider')]
    public function testRunModeRoundTrip(RunMode $runMode): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->add(new Subscription('foo', runMode: $runMode));

        self::assertSame($runMode, $store->get('foo')->runMode());
    }

    public static function runModeProvider(): Generator
    {
        foreach (RunMode::cases() as $runMode) {
            yield $runMode->value => [$runMode];
        }
    }

    public function testMaxValues(): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $id = str_repeat('a', 255);
        $group = str_repeat('b', 32);

        $store->add(new Subscription($id, $group, position: 2147483647, retryAttempt: 2147483647));

        $loaded = $store->get($id);

        self::assertSame($id, $loaded->id());
        self::assertSame($group, $loaded->group());
        self::assertSame(2147483647, $loaded->position());
        self::assertSame(2147483647, $loaded->retryAttempt());
    }

    public function testUpdateWithNestedErrors(): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->add(new Subscription('foo', status: Status::Booting));

        $subscription = $store->get('foo');
        $subscription->error(new RuntimeException('outer', 0, new LogicException('inner')));

        $store->update($subscription);

        $error = $store->get('foo')->subscriptionError();

        self::assertNotNull($error);
        self::assertSame('outer', $error->errorMessage);
        self::assertSame(Status::Booting, $error->previousStatus);
        self::assertNotNull($error->errorContext);
        self::assertCount(2, $error->errorContext);
        self::assertSame(RuntimeException::class, $error->errorContext[0]['class']);
        self::assertSame('outer', $error->errorContext[0]['message']);
        self::assertSame(LogicException::class, $error->errorContext[1]['class']);
        self::assertSame('inner', $error->errorContext[1]['message']);
    }

    public function testUpdateWithLongUnicodeErrorMessage(): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        // no 4-byte characters like emojis, the ci uses charset=utf8 (utf8mb3) for mysql and mariadb
        $message = str_repeat('Fehler: äöü ß € ', 1000);

        $store->add(new Subscription('foo'));

        $subscription = $store->get('foo');
        $subscription->error(new RuntimeException($message));

        $store->update($subscription);

        $error = $store->get('foo')->subscriptionError();

        self::assertNotNull($error);
        self::assertSame($message, $error->errorMessage);
        self::assertNotNull($error->errorContext);
        self::assertSame($message, $error->errorContext[0]['message']);
    }

    public function testMultipleCleanupTasks(): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $tasks = [
            new DropTableTask('foo', 'connection'),
            new DropIndexTask('bar_idx', 'bar'),
        ];

        $store->add(new Subscription('foo', cleanupTasks: $tasks));

        self::assertEquals($tasks, $store->get('foo')->cleanupTasks());
    }

    public function testRemoveAndAddAgain(): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->add(new Subscription('foo', position: 42));
        $store->remove($store->get('foo'));

        $store->add(new Subscription('foo'));

        self::assertNull($store->get('foo')->position());
    }

    public function testFindHydratesAllFields(): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $subscription = new Subscription(
            'foo',
            'bar',
            RunMode::Once,
            Status::Active,
            10,
            null,
            3,
            null,
            [new DropTableTask('baz')],
        );
        $subscription->error('error message');

        $store->add($subscription);

        $found = $store->find(new SubscriptionCriteria(ids: ['foo']));

        self::assertCount(1, $found);
        self::assertEquals($subscription, $found[0]);
    }

    public function testFindByUnknownGroup(): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $store,
        );

        $schemaDirector->create();

        $store->add(new Subscription('foo'));

        self::assertSame([], $store->find(new SubscriptionCriteria(groups: ['unknown'])));
    }

    public function testConfigureSchemaForOtherDatabase(): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $otherConnection = DbalManager::createConnection('other');

        try {
            $schema = new Schema();
            $store->configureSchema($schema, $otherConnection);

            self::assertFalse($schema->hasTable('subscriptions'));
        } finally {
            $otherConnection->close();
        }
    }

    public function testConfigureSchemaForSameDatabase(): void
    {
        $store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $schema = new Schema();
        $store->configureSchema($schema, $this->connection);

        self::assertTrue($schema->hasTable('subscriptions'));
    }

    /**
     * @param list<Subscription> $subscriptions
     *
     * @return list<string>
     */
    private function ids(array $subscriptions): array
    {
        return array_map(static fn (Subscription $subscription) => $subscription->id(), $subscriptions);
    }
}
