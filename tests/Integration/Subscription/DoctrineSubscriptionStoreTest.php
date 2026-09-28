<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Patchlevel\EventSourcing\Clock\FrozenClock;
use Patchlevel\EventSourcing\Schema\DoctrineSchemaDirector;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\DoctrineSubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionNotFound;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Tests\DbalManager;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
final class DoctrineSubscriptionStoreTest extends TestCase
{
    private Connection $connection;
    private DoctrineSubscriptionStore $store;

    public function setUp(): void
    {
        $this->connection = DbalManager::createConnection();

        $this->store = new DoctrineSubscriptionStore(
            $this->connection,
            new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00')),
        );

        $schemaDirector = new DoctrineSchemaDirector(
            $this->connection,
            $this->store,
        );

        $schemaDirector->create();
    }

    public function tearDown(): void
    {
        $this->connection->close();
    }

    public function testUpdate(): void
    {
        $this->store->add(new Subscription('foo'));

        $subscription = $this->store->get('foo');
        $subscription->active();
        $subscription->changePosition(42);

        $this->store->update($subscription);

        $loaded = $this->store->get('foo');

        self::assertSame(Status::Active, $loaded->status());
        self::assertSame(42, $loaded->position());
    }

    public function testUpdateWithoutChanges(): void
    {
        $this->store->add(new Subscription('foo'));

        // the clock is frozen, so all values stay the same and mysql reports no affected rows
        $this->store->update($this->store->get('foo'));
        $this->store->update($this->store->get('foo'));

        self::assertSame('foo', $this->store->get('foo')->id());
    }

    public function testUpdateUnknownSubscription(): void
    {
        $this->expectException(SubscriptionNotFound::class);

        $this->store->update(new Subscription('foo'));
    }
}
