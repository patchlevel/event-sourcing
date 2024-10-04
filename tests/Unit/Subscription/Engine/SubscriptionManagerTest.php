<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine;

use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\Store\LockableSubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Subscription;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/** @covers \Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager */
final class SubscriptionManagerTest extends TestCase
{
    use ProphecyTrait;

    public function testAdd(): void
    {
        $subscription = new Subscription('foo');

        $store = $this->prophesize(SubscriptionStore::class);
        $store->add($subscription)->shouldBeCalledOnce();

        $manager = new SubscriptionManager($store->reveal());
        $manager->add($subscription);
        $manager->flush();
    }

    public function testUpdate(): void
    {
        $subscription = new Subscription('foo');

        $store = $this->prophesize(SubscriptionStore::class);
        $store->update($subscription)->shouldBeCalledOnce();

        $manager = new SubscriptionManager($store->reveal());
        $manager->update($subscription);
        $manager->flush();
    }

    public function testRemove(): void
    {
        $subscription = new Subscription('foo');

        $store = $this->prophesize(SubscriptionStore::class);
        $store->remove($subscription)->shouldBeCalledOnce();

        $manager = new SubscriptionManager($store->reveal());
        $manager->remove($subscription);
        $manager->flush();
    }

    public function testDontUpdateIfNewAdded(): void
    {
        $subscription = new Subscription('foo');

        $store = $this->prophesize(SubscriptionStore::class);
        $store->add($subscription)->shouldBeCalledOnce();
        $store->update($subscription)->shouldNotBeCalled();

        $manager = new SubscriptionManager($store->reveal());
        $manager->add($subscription);
        $manager->update($subscription);
        $manager->flush();
    }

    public function testDontUpdateIfRemoved(): void
    {
        $subscription = new Subscription('foo');

        $store = $this->prophesize(SubscriptionStore::class);
        $store->remove($subscription)->shouldBeCalledOnce();
        $store->update($subscription)->shouldNotBeCalled();

        $manager = new SubscriptionManager($store->reveal());
        $manager->update($subscription);
        $manager->remove($subscription);
        $manager->flush();
    }

    public function testDoNothingIfAddAndRemoved(): void
    {
        $subscription = new Subscription('foo');

        $store = $this->prophesize(SubscriptionStore::class);
        $store->remove($subscription)->shouldNotBeCalled();
        $store->add($subscription)->shouldNotBeCalled();

        $manager = new SubscriptionManager($store->reveal());
        $manager->add($subscription);
        $manager->remove($subscription);
        $manager->flush();
    }

    public function testFind(): void
    {
        $subscription = new Subscription('foo');
        $criteria = new SubscriptionCriteria();

        $store = $this->prophesize(SubscriptionStore::class);
        $store->find($criteria)->shouldBeCalledOnce()->willReturn([$subscription]);

        $manager = new SubscriptionManager($store->reveal());
        $result = $manager->find($criteria);

        self::assertSame([$subscription], $result);
    }

    public function testFindForUpdateWithoutLock(): void
    {
        $subscription = new Subscription('foo');
        $criteria = new SubscriptionCriteria();

        $store = $this->prophesize(SubscriptionStore::class);
        $store->update($subscription)->shouldBeCalledOnce();
        $store->find($criteria)->shouldBeCalledOnce()->willReturn([$subscription]);

        $manager = new SubscriptionManager($store->reveal());
        $result = $manager->findForUpdate($criteria, static function ($subscriptions) use ($manager) {
            $manager->update(...$subscriptions);

            return $subscriptions;
        });

        self::assertSame([$subscription], $result);
    }

    public function testFindForUpdateWithLock(): void
    {
        $subscription = new Subscription('foo');
        $criteria = new SubscriptionCriteria();

        $store = $this->prophesize(SubscriptionStore::class);
        $store->willImplement(LockableSubscriptionStore::class);
        $store->update($subscription)->shouldBeCalledOnce();
        $store->find($criteria)->shouldBeCalledOnce()->willReturn([$subscription]);

        $store->inLock(Argument::any())->will(
        /** @param array{0: callable} $args */
            static fn (array $args): mixed => $args[0](),
        )->shouldBeCalledOnce();

        $manager = new SubscriptionManager($store->reveal());
        $result = $manager->findForUpdate($criteria, static function ($subscriptions) use ($manager) {
            $manager->update(...$subscriptions);

            return $subscriptions;
        });

        self::assertSame([$subscription], $result);
    }
}
