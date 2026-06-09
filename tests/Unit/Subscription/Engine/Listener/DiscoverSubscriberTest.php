<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine\Listener;

use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Boot;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Pause;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Reactivate;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Remove;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Run;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Teardown;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnCommand;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnSubscriptions;
use Patchlevel\EventSourcing\Subscription\Engine\Listener\DiscoverSubscriber;
use Patchlevel\EventSourcing\Subscription\Engine\MessageLoader;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\LockableSubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Tests\Unit\Subscription\DummySubscriptionStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(DiscoverSubscriber::class)]
final class DiscoverSubscriberTest extends TestCase
{
    private function createListener(DummySubscriptionStore $store, array $subscribers = []): DiscoverSubscriber
    {
        return new DiscoverSubscriber(
            $this->createMock(MessageLoader::class),
            new SubscriptionManager($store),
            new MetadataSubscriberAccessorRepository($subscribers),
            new NullLogger(),
        );
    }

    public function testBootDiscoverNewSubscribers(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $store = new DummySubscriptionStore();
        $listener = $this->createListener($store, [$subscriber]);

        $listener->onCommand(new OnCommand(new Boot()));

        $store->assertAdded(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::New,
            ),
        );
    }

    public function testRunDiscoverNewSubscribers(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $store = new DummySubscriptionStore();
        $listener = $this->createListener($store, [$subscriber]);

        $listener->onCommand(new OnCommand(new Run()));

        $store->assertAdded(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::New,
            ),
        );
    }

    public function testTeardownDiscoverNewSubscribers(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $store = new DummySubscriptionStore();
        $listener = $this->createListener($store, [$subscriber]);

        $listener->onCommand(new OnCommand(new Teardown()));

        $store->assertAdded(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::New,
            ),
        );
    }

    public function testRemoveDiscoverNewSubscribers(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $store = new DummySubscriptionStore();
        $listener = $this->createListener($store, [$subscriber]);

        $listener->onCommand(new OnCommand(new Remove()));

        $store->assertAdded(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::New,
            ),
        );
    }

    public function testReactiveDiscoverNewSubscribers(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $store = new DummySubscriptionStore();
        $listener = $this->createListener($store, [$subscriber]);

        $listener->onCommand(new OnCommand(new Reactivate()));

        $store->assertAdded(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::New,
            ),
        );
    }

    public function testPauseDiscoverNewSubscribers(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $store = new DummySubscriptionStore();
        $listener = $this->createListener($store, [$subscriber]);

        $listener->onCommand(new OnCommand(new Pause()));

        $store->assertAdded(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::New,
            ),
        );
    }

    public function testGetSubscriptionAndDiscoverNewSubscribers(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $store = new DummySubscriptionStore();
        $listener = $this->createListener($store, [$subscriber]);

        $listener->onSubscriptions(new OnSubscriptions(new SubscriptionEngineCriteria()));

        $store->assertAdded(
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::New,
            ),
        );
    }

    public function testDontLockGetSubscriptions(): void
    {
        $subscriber = new #[Subscriber('id1', RunMode::FromNow)]
        class {
        };

        $subscriptionStore = $this->createMock(LockableSubscriptionStore::class);
        $subscriptionStore
            ->expects($this->never())
            ->method('inLock');

        $subscriptionStore
            ->expects($this->once())
            ->method('find')
            ->with(new SubscriptionCriteria())
            ->willReturn([new Subscription('id1')]);

        $subscriptionStore
            ->expects($this->never())
            ->method('add');

        $listener = new DiscoverSubscriber(
            $this->createMock(MessageLoader::class),
            new SubscriptionManager($subscriptionStore),
            new MetadataSubscriberAccessorRepository([$subscriber]),
            new NullLogger(),
        );

        $listener->onSubscriptions(new OnSubscriptions(new SubscriptionEngineCriteria()));
    }
}
