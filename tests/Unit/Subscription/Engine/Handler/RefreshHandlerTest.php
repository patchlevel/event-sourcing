<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine\Handler;

use Patchlevel\EventSourcing\Attribute\Cleanup;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Subscription\Cleanup\Dbal\DropTableTask;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Refresh as RefreshCommand;
use Patchlevel\EventSourcing\Subscription\Engine\Handler\RefreshHandler;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Tests\Unit\Subscription\DummySubscriptionStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(RefreshHandler::class)]
final class RefreshHandlerTest extends TestCase
{
    /** @param list<object> $subscribers */
    private function createHandler(DummySubscriptionStore $store, array $subscribers = []): RefreshHandler
    {
        return new RefreshHandler(
            new SubscriptionManager($store),
            new MetadataSubscriberAccessorRepository($subscribers),
            new NullLogger(),
        );
    }

    public function testRefreshSubscriptionsNoChanges(): void
    {
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning, group: 'default')]
        class {
        };

        $subscription = new Subscription('test', 'default', RunMode::FromBeginning, Status::Active);
        $store = new DummySubscriptionStore([$subscription]);

        $handler = $this->createHandler($store, [$subscriber]);
        $handler(new RefreshCommand());

        $store->assertNoChanges();
    }

    public function testRefreshSubscriptionsChangeRunMode(): void
    {
        $subscriber = new #[Subscriber('test', RunMode::FromNow)]
        class {
        };

        $subscription = new Subscription('test', 'default', RunMode::FromBeginning, Status::Active);
        $store = new DummySubscriptionStore([$subscription]);

        $handler = $this->createHandler($store, [$subscriber]);
        $handler(new RefreshCommand());

        $store->assertUpdated(
            new Subscription('test', 'default', RunMode::FromNow, Status::Active),
        );
    }

    public function testRefreshSubscriptionsChangeGroup(): void
    {
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning, group: 'new-group')]
        class {
        };

        $subscription = new Subscription('test', 'default', RunMode::FromBeginning, Status::Active);
        $store = new DummySubscriptionStore([$subscription]);

        $handler = $this->createHandler($store, [$subscriber]);
        $handler(new RefreshCommand());

        $store->assertUpdated(
            new Subscription('test', 'new-group', RunMode::FromBeginning, Status::Active),
        );
    }

    public function testRefreshSubscriptionsChangeCleanupTasks(): void
    {
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            /** @return iterable<object> */
            #[Cleanup]
            public function cleanup(): iterable
            {
                yield new DropTableTask('test');
            }
        };

        $subscription = new Subscription('test', 'default', RunMode::FromBeginning, Status::Active);
        $store = new DummySubscriptionStore([$subscription]);

        $handler = $this->createHandler($store, [$subscriber]);
        $handler(new RefreshCommand());

        $store->assertUpdated(
            new Subscription('test', 'default', RunMode::FromBeginning, Status::Active, cleanupTasks: [new DropTableTask('test')]),
        );
    }

    public function testRefreshSubscriptionsMultipleChanges(): void
    {
        $subscriber = new #[Subscriber('test', RunMode::FromNow, group: 'new-group')]
        class {
            /** @return iterable<object> */
            #[Cleanup]
            public function cleanup(): iterable
            {
                yield new DropTableTask('test');
            }
        };

        $subscription = new Subscription('test', 'default', RunMode::FromBeginning, Status::Active);
        $store = new DummySubscriptionStore([$subscription]);

        $handler = $this->createHandler($store, [$subscriber]);
        $handler(new RefreshCommand());

        $store->assertUpdated(
            new Subscription('test', 'new-group', RunMode::FromNow, Status::Active, cleanupTasks: [new DropTableTask('test')]),
        );
    }

    public function testRefreshWithMissingSubscriber(): void
    {
        $subscriptionId = 'test';

        $store = new DummySubscriptionStore([new Subscription($subscriptionId)]);

        $handler = $this->createHandler($store);
        $result = $handler(new RefreshCommand());

        self::assertEquals([], $result->errors);
        self::assertSame([], $store->updatedSubscriptions);
    }

}
