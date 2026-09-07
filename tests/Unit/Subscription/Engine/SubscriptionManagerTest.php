<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine;

use Closure;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Store\TransactionCommitNotPossible;
use Patchlevel\EventSourcing\Subscription\Subscription;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(SubscriptionManager::class)]
final class SubscriptionManagerTest extends TestCase
{
    public function testAdd(): void
    {
        $subscription = new Subscription('foo');

        $store = $this->createMock(SubscriptionStore::class);
        $store->expects($this->once())->method('add')->with($subscription);

        $manager = new SubscriptionManager($store);
        $manager->add($subscription);
        $manager->flush();
    }

    public function testUpdate(): void
    {
        $subscription = new Subscription('foo');

        $store = $this->createMock(SubscriptionStore::class);
        $store->expects($this->once())->method('update')->with($subscription);

        $manager = new SubscriptionManager($store);
        $manager->update($subscription);
        $manager->flush();
    }

    public function testRemove(): void
    {
        $subscription = new Subscription('foo');

        $store = $this->createMock(SubscriptionStore::class);
        $store->expects($this->once())->method('remove')->with($subscription);

        $manager = new SubscriptionManager($store);
        $manager->remove($subscription);
        $manager->flush();
    }

    public function testDontUpdateIfNewAdded(): void
    {
        $subscription = new Subscription('foo');

        $store = $this->createMock(SubscriptionStore::class);
        $store->expects($this->once())->method('add')->with($subscription);
        $store->expects($this->never())->method('update')->with($subscription);

        $manager = new SubscriptionManager($store);
        $manager->add($subscription);
        $manager->update($subscription);
        $manager->flush();
    }

    public function testDontUpdateIfRemoved(): void
    {
        $subscription = new Subscription('foo');

        $store = $this->createMock(SubscriptionStore::class);
        $store->expects($this->once())->method('remove')->with($subscription);
        $store->expects($this->never())->method('update')->with($subscription);

        $manager = new SubscriptionManager($store);
        $manager->update($subscription);
        $manager->remove($subscription);
        $manager->flush();
    }

    public function testDoNothingIfAddAndRemoved(): void
    {
        $subscription = new Subscription('foo');

        $store = $this->createMock(SubscriptionStore::class);
        $store->expects($this->never())->method('remove')->with($subscription);
        $store->expects($this->never())->method('add')->with($subscription);

        $manager = new SubscriptionManager($store);
        $manager->add($subscription);
        $manager->remove($subscription);
        $manager->flush();
    }

    public function testFind(): void
    {
        $subscription = new Subscription('foo');
        $criteria = new SubscriptionCriteria();

        $store = $this->createMock(SubscriptionStore::class);
        $store->expects($this->once())->method('find')->with($criteria)->willReturn([$subscription]);

        $manager = new SubscriptionManager($store);
        $result = $manager->find($criteria);

        self::assertSame([$subscription], $result);
    }

    public function testForEachClaimedProcessesClaimedSubscription(): void
    {
        $subscription = new Subscription('foo');
        $criteria = new SubscriptionCriteria();

        $store = $this->createMock(SubscriptionStore::class);
        $store->expects($this->once())->method('find')->with($criteria)->willReturn([$subscription]);
        $store->expects($this->once())->method('claim')->with('foo', $criteria)->willReturn($subscription);
        $store->expects($this->once())->method('update')->with($subscription);
        $store
            ->expects($this->once())
            ->method('inLock')
            ->with($this->isInstanceOf(Closure::class))
            ->willReturnCallback(static fn (Closure $closure): mixed => $closure());

        $manager = new SubscriptionManager($store);
        $result = $manager->forEachClaimed($criteria, static function (Subscription $claimed) use ($manager) {
            $manager->update($claimed);

            return $claimed;
        });

        self::assertSame([$subscription], $result);
    }

    public function testForEachClaimedSkipsWhenClaimReturnsNull(): void
    {
        $subscription = new Subscription('foo');
        $criteria = new SubscriptionCriteria();

        $store = $this->createMock(SubscriptionStore::class);
        $store->expects($this->once())->method('find')->with($criteria)->willReturn([$subscription]);
        $store->expects($this->once())->method('claim')->with('foo', $criteria)->willReturn(null);
        $store->expects($this->never())->method('update');
        $store
            ->expects($this->once())
            ->method('inLock')
            ->willReturnCallback(static fn (Closure $closure): mixed => $closure());

        $manager = new SubscriptionManager($store);
        $result = $manager->forEachClaimed($criteria, static fn (Subscription $claimed) => $claimed);

        self::assertSame([], $result);
    }

    public function testForEachClaimedIsolatesTransientErrors(): void
    {
        $subscription = new Subscription('foo');
        $criteria = new SubscriptionCriteria();

        $store = $this->createMock(SubscriptionStore::class);
        $store->expects($this->once())->method('find')->with($criteria)->willReturn([$subscription]);
        $store->expects($this->once())->method('claim')->with('foo', $criteria)->willReturn($subscription);
        $store
            ->expects($this->once())
            ->method('inLock')
            ->willReturnCallback(static fn (Closure $closure): mixed => $closure());

        $manager = new SubscriptionManager($store);
        $result = $manager->forEachClaimed(
            $criteria,
            static function (): never {
                throw new TransactionCommitNotPossible(new RuntimeException('deadlock'));
            },
        );

        self::assertSame([], $result);
    }
}
