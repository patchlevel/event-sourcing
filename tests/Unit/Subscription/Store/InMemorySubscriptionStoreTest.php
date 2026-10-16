<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Store;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Clock\FrozenClock;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\InboxStats;
use Patchlevel\EventSourcing\Subscription\Store\InMemorySubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionAlreadyExists;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionNotFound;
use Patchlevel\EventSourcing\Subscription\Subscription;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

use function array_map;

#[CoversClass(InMemorySubscriptionStore::class)]
final class InMemorySubscriptionStoreTest extends TestCase
{
    public function testAdd(): void
    {
        $now = new DateTimeImmutable('2021-01-01 00:00:00');

        $store = new InMemorySubscriptionStore(
            clock: new FrozenClock($now),
        );

        $id = 'test';
        $subscription = new Subscription($id);

        $store->add($subscription);

        self::assertEquals($subscription, $store->get($id));
        self::assertEquals([$subscription], $store->find());
        self::assertEquals($now, $subscription->lastSavedAt());
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
        $now = new DateTimeImmutable('2021-01-01 00:00:00');

        $id = 'test';
        $subscription = new Subscription($id);

        $store = new InMemorySubscriptionStore([$subscription], new FrozenClock($now));

        $store->update($subscription);

        self::assertEquals($subscription, $store->get($id));
        self::assertEquals([$subscription], $store->find());
        self::assertEquals($now, $subscription->lastSavedAt());
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

    public function testStats(): void
    {
        $main = new Subscription('profile', status: Status::Active, position: 10);
        $broken = Subscription::partition($main, 'broken', 3);
        $broken->error('database down');

        $store = new InMemorySubscriptionStore([
            $main,
            Subscription::partition($main, 'a', 5),
            $broken,
            new Subscription('other', status: Status::Paused),
        ]);

        $stats = $store->stats(new SubscriptionCriteria(ids: ['profile'], partitions: true));

        self::assertSame(['active' => 1, 'error' => 1], $stats->status);
        self::assertSame(2, $stats->total());
        self::assertSame(1, $stats->count(Status::Error));
        self::assertSame(0, $stats->count(Status::Failed));
        self::assertSame(['active' => 2, 'error' => 1, 'paused' => 1], $store->stats(new SubscriptionCriteria())->status);
    }

    public function testInboxStats(): void
    {
        $main = new Subscription('profile', status: Status::Active, position: 10);
        $broken = Subscription::partition($main, 'broken', 3);
        $broken->error('database down');

        $store = new InMemorySubscriptionStore([
            $main,
            Subscription::partition($main, 'a', 5),
            Subscription::partition($main, 'b', 6),
            $broken,
            new Subscription('other', status: Status::Paused),
        ]);
        $store->addToInbox([
            'profile#a' => [6, 7],
            'profile#b' => [6, 8],
            'profile#broken' => [9],
        ]);

        self::assertEquals(
            new InboxStats(4, 3),
            $store->inboxStats(new SubscriptionCriteria(ids: ['profile'], partitions: true)),
            'messages up to the position of a partition are not counted',
        );
        self::assertEquals(new InboxStats(1, 1), $store->inboxStats(new SubscriptionCriteria(status: [Status::Error])));
        self::assertEquals(new InboxStats(), $store->inboxStats(new SubscriptionCriteria(ids: ['other'])));
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

    public function testClear(): void
    {
        $subscription1 = new Subscription('1', group: 'group1', status: Status::New);
        $subscription2 = new Subscription('2', group: 'group2', status: Status::Booting);

        $store = new InMemorySubscriptionStore([$subscription1, $subscription2]);
        $store->clear();

        self::assertSame([], $store->find());
    }

    public function testClaim(): void
    {
        $subscription = new Subscription('foo', status: Status::Active);

        $store = new InMemorySubscriptionStore([$subscription]);

        self::assertSame(
            [$subscription],
            $store->claim(['foo'], new SubscriptionCriteria(status: [Status::Active])),
        );
    }

    public function testClaimNotFound(): void
    {
        $store = new InMemorySubscriptionStore();

        self::assertSame([], $store->claim(['foo'], new SubscriptionCriteria()));
    }

    public function testClaimCriteriaNotMatched(): void
    {
        $subscription = new Subscription('foo', status: Status::New);

        $store = new InMemorySubscriptionStore([$subscription]);

        self::assertSame([], $store->claim(['foo'], new SubscriptionCriteria(status: [Status::Active])));
    }

    public function testInLock(): void
    {
        $store = new InMemorySubscriptionStore();

        $subscription = new Subscription('foo');

        self::assertSame($subscription, $store->inLock(static fn (): Subscription => $subscription));
    }

    public function testFindPartitions(): void
    {
        $store = new InMemorySubscriptionStore();

        $main = new Subscription('profile', status: Status::Active, position: 0);
        $partition = Subscription::partition($main, 'tenant-a', 0);
        $other = new Subscription('other');

        $store->add($other);
        $store->add($main);
        $store->add($partition);

        self::assertSame(
            ['profile', 'profile#tenant-a'],
            array_map(static fn (Subscription $s) => $s->id(), $store->find(new SubscriptionCriteria(ids: ['profile']))),
            'the subscriber id includes its partitions',
        );
        self::assertSame(
            ['profile#tenant-a'],
            array_map(static fn (Subscription $s) => $s->id(), $store->find(new SubscriptionCriteria(ids: ['profile#tenant-a']))),
            'a single partition can be targeted by its id',
        );
        self::assertSame(
            ['other', 'profile'],
            array_map(static fn (Subscription $s) => $s->id(), $store->find(new SubscriptionCriteria(partitions: false))),
        );
        self::assertSame(
            ['profile#tenant-a'],
            array_map(static fn (Subscription $s) => $s->id(), $store->find(new SubscriptionCriteria(partitions: true))),
        );

        $found = $store->get('profile#tenant-a');
        self::assertSame('profile', $found->subscriberId());
        self::assertSame('tenant-a', $found->partitionKey());
    }

    public function testInbox(): void
    {
        $store = new InMemorySubscriptionStore();

        $main = new Subscription('profile', status: Status::Active, position: 0);
        $a = Subscription::partition($main, 'a', 0);
        $b = Subscription::partition($main, 'b', 0);
        $store->add($main, $a, $b);

        $store->addToInbox(['profile#a' => [5, 3, 9], 'profile#b' => [4]]);

        self::assertSame(['profile#a' => [3, 5, 9]], $store->inbox(['profile#a' => -1], 10));
        self::assertSame(['profile#a' => [5, 9]], $store->inbox(['profile#a' => 3], 10));
        self::assertSame(
            ['profile#a' => [3, 5], 'profile#b' => [4]],
            $store->inbox(['profile#a' => -1, 'profile#b' => -1], 2),
            'the limit applies to every subscription',
        );
        self::assertSame([], $store->inbox(['profile#c' => -1], 10), 'an empty inbox is left out');

        $a->changePosition(5);
        $b->changePosition(3);
        $store->update($a, $b);

        self::assertSame(
            ['profile#a' => [9], 'profile#b' => [4]],
            $store->inbox(['profile#a' => -1, 'profile#b' => -1], 10),
            'updating a partition clears its inbox up to its position',
        );
    }

    public function testPendingPartitionsOnly(): void
    {
        $store = new InMemorySubscriptionStore();

        $main = new Subscription('profile', status: Status::Active, position: 0);
        $store->add($main);
        $store->add(Subscription::partition($main, 'pending', 3));
        $store->add(Subscription::partition($main, 'processed', 7));
        $store->add(Subscription::partition($main, 'empty', 0));

        $store->addToInbox(['profile#pending' => [4], 'profile#processed' => [7]]);

        self::assertSame(
            ['profile', 'profile#pending'],
            array_map(
                static fn (Subscription $s) => $s->id(),
                $store->find(new SubscriptionCriteria(pendingPartitionsOnly: true)),
            ),
        );

        self::assertNotSame([], $store->claim(['profile#pending'], new SubscriptionCriteria(pendingPartitionsOnly: true)));
        self::assertSame([], $store->claim(['profile#empty'], new SubscriptionCriteria(pendingPartitionsOnly: true)));
    }

    public function testRemoveAlsoRemovesTheInbox(): void
    {
        $store = new InMemorySubscriptionStore();

        $partition = Subscription::partition(new Subscription('profile'), 'a', 0);
        $store->add($partition);
        $store->addToInbox([$partition->id() => [1, 2]]);

        $store->remove($partition);
        $store->add($partition);

        self::assertSame([], $store->inbox([$partition->id() => -1], 10));
    }

    public function testClaimAll(): void
    {
        $store = new InMemorySubscriptionStore();

        $store->add(new Subscription('a', status: Status::Active));
        $store->add(new Subscription('b', status: Status::Paused));
        $store->add(new Subscription('c', status: Status::Active));

        self::assertSame(
            ['a', 'c'],
            array_map(
                static fn (Subscription $s) => $s->id(),
                $store->claim(['a', 'b', 'c', 'unknown'], new SubscriptionCriteria(status: [Status::Active])),
            ),
        );
    }

    public function testAddAllAndUpdateAll(): void
    {
        $store = new InMemorySubscriptionStore();

        $store->add(...[new Subscription('a'), new Subscription('b')]);

        $a = $store->get('a');
        $a->changePosition(5);
        $store->update(...[$a, $store->get('b')]);

        self::assertSame(5, $store->get('a')->position());
        self::assertCount(2, $store->find());
    }
}
