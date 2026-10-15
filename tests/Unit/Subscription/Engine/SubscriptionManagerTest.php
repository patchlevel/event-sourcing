<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine;

use Closure;
use Patchlevel\EventSourcing\Subscription\Engine\ClaimGroups;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\InMemorySubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Store\TransactionCommitNotPossible;
use Patchlevel\EventSourcing\Subscription\Subscription;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

use function array_map;
use function count;

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
        $store->expects($this->once())->method('claim')->with(['foo'], $criteria)->willReturn([$subscription]);
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

    public function testForEachClaimedSkipsWhenNothingIsClaimed(): void
    {
        $subscription = new Subscription('foo');
        $criteria = new SubscriptionCriteria();

        $store = $this->createMock(SubscriptionStore::class);
        $store->expects($this->once())->method('find')->with($criteria)->willReturn([$subscription]);
        $store->expects($this->once())->method('claim')->with(['foo'], $criteria)->willReturn([]);
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
        $store->expects($this->once())->method('claim')->with(['foo'], $criteria)->willReturn([$subscription]);
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

    public function testForEachClaimedGroupKeepsPartitionsAndSubscriptionsApart(): void
    {
        $main = new Subscription('a');
        $store = new InMemorySubscriptionStore([
            $main,
            Subscription::partition($main, '1', 0),
            Subscription::partition($main, '2', 0),
            Subscription::partition($main, '3', 0),
            new Subscription('b'),
            new Subscription('c'),
        ]);

        $manager = new SubscriptionManager($store);

        $groups = $manager->forEachClaimedGroup(
            new SubscriptionCriteria(),
            new ClaimGroups(subscriptions: 2, partitions: 2),
            static fn (array $subscriptions): array => array_map(
                static fn (Subscription $subscription): string => $subscription->id(),
                $subscriptions,
            ),
        );

        self::assertSame([['a', 'b'], ['c'], ['a#1', 'a#2'], ['a#3']], $groups);
    }

    public function testForEachClaimedGroupPutsTheWholeGroupIntoError(): void
    {
        $store = new InMemorySubscriptionStore([
            new Subscription('a', status: Status::Active),
            new Subscription('b', status: Status::Active),
        ]);

        $manager = new SubscriptionManager($store);

        $results = $manager->forEachClaimedGroup(
            new SubscriptionCriteria(),
            new ClaimGroups(subscriptions: 2),
            static fn (array $subscriptions): never => throw new RuntimeException('boom'),
            static fn (array $subscriptions, Throwable $e): int => count($subscriptions),
        );

        self::assertSame([2], $results);
        self::assertSame(Status::Error, $store->get('a')->status());
        self::assertSame(Status::Error, $store->get('b')->status());
    }

    public function testForEachClaimedGroupKeepsTheErrorOfAMemberThatAlreadyFailed(): void
    {
        $store = new InMemorySubscriptionStore([
            new Subscription('a', status: Status::Active),
            new Subscription('b', status: Status::Active),
        ]);

        $manager = new SubscriptionManager($store);

        $manager->forEachClaimedGroup(
            new SubscriptionCriteria(),
            new ClaimGroups(subscriptions: 2),
            static function (array $subscriptions): never {
                // the first one failed on its own before the whole group failed
                $subscriptions[0]->error('handler failed');

                throw new RuntimeException('boom');
            },
        );

        $a = $store->get('a');
        self::assertSame('handler failed', $a->subscriptionError()?->errorMessage);
        self::assertSame(Status::Active, $a->subscriptionError()->previousStatus, 'it is still retried');
        self::assertSame('boom', $store->get('b')->subscriptionError()?->errorMessage);
    }

    public function testForEachClaimedGroupOnlyGroupsSubscriptionsWithClosePositions(): void
    {
        $store = new InMemorySubscriptionStore([
            new Subscription('rebuild', position: 0),
            new Subscription('far', position: 50_000),
            new Subscription('current-1', position: 2_003),
            new Subscription('almost-rebuild', position: 5),
            new Subscription('current-2', position: 2_000),
            new Subscription('new'),
        ]);

        $manager = new SubscriptionManager($store);

        $groups = $manager->forEachClaimedGroup(
            new SubscriptionCriteria(),
            new ClaimGroups(subscriptions: 10, positionWindow: 1000),
            static fn (array $subscriptions): array => array_map(
                static fn (Subscription $subscription): string => $subscription->id(),
                $subscriptions,
            ),
        );

        self::assertSame(
            [['new', 'rebuild', 'almost-rebuild'], ['current-2', 'current-1'], ['far']],
            $groups,
        );
    }

    public function testForEachClaimedKeepsTheOrderOfTheSnapshot(): void
    {
        $store = new InMemorySubscriptionStore([
            new Subscription('b', position: 100),
            new Subscription('a', position: 0),
        ]);

        $manager = new SubscriptionManager($store);

        self::assertSame(
            ['b', 'a'],
            $manager->forEachClaimed(new SubscriptionCriteria(), static fn (Subscription $subscription): string => $subscription->id()),
        );
    }

    public function testSupportsPartitionsOnlyWithAnInboxStore(): void
    {
        self::assertFalse((new SubscriptionManager($this->createMock(SubscriptionStore::class)))->supportsPartitions());
        self::assertTrue((new SubscriptionManager(new InMemorySubscriptionStore()))->supportsPartitions());
    }
}
