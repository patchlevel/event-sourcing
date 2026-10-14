<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine\Listener;

use Patchlevel\EventSourcing\Subscription\Engine\Event\OnSubscriptionRemoved;
use Patchlevel\EventSourcing\Subscription\Engine\Listener\PartitionSubscriber;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Tests\Unit\Subscription\DummySubscriptionStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[CoversClass(PartitionSubscriber::class)]
final class PartitionSubscriberTest extends TestCase
{
    public function testRemovesPartitionsAndDispatchesRemovedEvent(): void
    {
        $main = new Subscription('profile', Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Active);
        $partitionA = Subscription::partition($main, 'a', null, 0);
        $partitionB = Subscription::partition($main, 'b', null, 0);
        $other = new Subscription('other', Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Active);

        $store = new DummySubscriptionStore([$main, $partitionA, $partitionB, $other]);
        $subscriptionManager = new SubscriptionManager($store);

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new PartitionSubscriber($subscriptionManager, $eventDispatcher, new NullLogger()));

        $removed = [];
        $eventDispatcher->addListener(
            OnSubscriptionRemoved::class,
            static function (OnSubscriptionRemoved $event) use (&$removed): void {
                $removed[] = $event->subscription->id();
            },
        );

        $eventDispatcher->dispatch(new OnSubscriptionRemoved($main));
        $subscriptionManager->flush();

        $store->assertRemoved($partitionA, $partitionB);
        self::assertSame(['profile#a', 'profile#b', 'profile'], $removed);
    }

    public function testIgnoresRemovedPartition(): void
    {
        $main = new Subscription('profile', Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Active);
        $partition = Subscription::partition($main, 'a', null, 0);

        $store = new DummySubscriptionStore([$main, $partition]);
        $subscriptionManager = new SubscriptionManager($store);

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new PartitionSubscriber($subscriptionManager, $eventDispatcher, new NullLogger()));

        $eventDispatcher->dispatch(new OnSubscriptionRemoved($partition));
        $subscriptionManager->flush();

        $store->assertNoChanges();
    }
}
