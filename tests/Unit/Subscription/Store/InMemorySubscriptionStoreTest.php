<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Store;

use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\InMemorySubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionAlreadyExists;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionNotFound;
use Patchlevel\EventSourcing\Subscription\Subscription;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Subscription\Store\InMemorySubscriptionStore */
final class InMemorySubscriptionStoreTest extends TestCase
{
    public function testAdd(): void
    {
        $store = new InMemorySubscriptionStore();

        $id = 'test';
        $subscription = new Subscription($id);

        $store->add($subscription);

        self::assertEquals($subscription, $store->get($id));
        self::assertEquals([$subscription], $store->find());
    }

    public function testAddDuplicated(): void
    {
        $this->expectException(SubscriptionAlreadyExists::class);

        $id = 'test';
        $subscription = new Subscription($id);

        $store = new InMemorySubscriptionStore([$subscription]);
        $store->add($subscription);
    }

    public function testUpdate(): void
    {
        $id = 'test';
        $subscription = new Subscription($id);

        $store = new InMemorySubscriptionStore([$subscription]);

        $store->update($subscription);

        self::assertEquals($subscription, $store->get($id));
        self::assertEquals([$subscription], $store->find());
    }

    public function testUpdateNotFound(): void
    {
        $this->expectException(SubscriptionNotFound::class);

        $id = 'test';
        $subscription = new Subscription($id);

        $store = new InMemorySubscriptionStore();

        $store->update($subscription);
    }

    public function testNotFound(): void
    {
        $this->expectException(SubscriptionNotFound::class);

        $store = new InMemorySubscriptionStore();
        $store->get('test');
    }

    public function testRemove(): void
    {
        $id = 'test';
        $subscription = new Subscription($id);

        $store = new InMemorySubscriptionStore([$subscription]);

        $store->remove($subscription);

        self::assertEquals([], $store->find());
    }

    public function testFind(): void
    {
        $subscription1 = new Subscription('1');
        $subscription2 = new Subscription('2');

        $store = new InMemorySubscriptionStore([$subscription1, $subscription2]);

        self::assertEquals([$subscription1, $subscription2], $store->find());
    }

    public function testFindById(): void
    {
        $subscription1 = new Subscription('1');
        $subscription2 = new Subscription('2');

        $store = new InMemorySubscriptionStore([$subscription1, $subscription2]);

        $criteria = new SubscriptionCriteria(
            ids: ['1'],
        );

        self::assertEquals([$subscription1], $store->find($criteria));
    }

    public function testFindByGroup(): void
    {
        $subscription1 = new Subscription('1', group: 'group1');
        $subscription2 = new Subscription('2', group: 'group2');

        $store = new InMemorySubscriptionStore([$subscription1, $subscription2]);

        $criteria = new SubscriptionCriteria(
            groups: ['group1'],
        );

        self::assertEquals([$subscription1], $store->find($criteria));
    }

    public function testFindByStatus(): void
    {
        $subscription1 = new Subscription('1', status: Status::New);
        $subscription2 = new Subscription('2', status: Status::Booting);

        $store = new InMemorySubscriptionStore([$subscription1, $subscription2]);

        $criteria = new SubscriptionCriteria(
            status: [Status::New],
        );

        self::assertEquals([$subscription1], $store->find($criteria));
    }

    public function testFindByAll(): void
    {
        $subscription1 = new Subscription('1', group: 'group1', status: Status::New);
        $subscription2 = new Subscription('2', group: 'group2', status: Status::Booting);

        $store = new InMemorySubscriptionStore([$subscription1, $subscription2]);

        $criteria = new SubscriptionCriteria(
            ids: ['1'],
            groups: ['group1'],
            status: [Status::New],
        );

        self::assertEquals([$subscription1], $store->find($criteria));
    }
}
