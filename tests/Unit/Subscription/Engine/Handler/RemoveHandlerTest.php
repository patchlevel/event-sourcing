<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine\Handler;

use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Attribute\Teardown;
use Patchlevel\EventSourcing\Subscription\Cleanup\CleanupFailed;
use Patchlevel\EventSourcing\Subscription\Cleanup\CleanupTaskHandler;
use Patchlevel\EventSourcing\Subscription\Cleanup\Dbal\DropTableTask;
use Patchlevel\EventSourcing\Subscription\Cleanup\DefaultCleaner;
use Patchlevel\EventSourcing\Subscription\Engine\CleanerNotConfigured;
use Patchlevel\EventSourcing\Subscription\Engine\CleanupRunner;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Remove as RemoveCommand;
use Patchlevel\EventSourcing\Subscription\Engine\Handler\RemoveHandler;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Tests\Unit\Subscription\DummySubscriptionStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventDispatcher;

#[CoversClass(RemoveHandler::class)]
final class RemoveHandlerTest extends TestCase
{
    /** @param list<object> $subscribers */
    private function createHandler(
        DummySubscriptionStore $store,
        array $subscribers = [],
        CleanupRunner|null $cleanupRunner = null,
    ): RemoveHandler {
        $subscriptionManager = new SubscriptionManager($store);

        return new RemoveHandler(
            $subscriptionManager,
            new MetadataSubscriberAccessorRepository($subscribers),
            $cleanupRunner ?? new CleanupRunner($subscriptionManager, null, new NullLogger()),
            new EventDispatcher(),
            new NullLogger(),
        );
    }

    public function testRemoveWithSubscriber(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            public bool $dropped = false;

            #[Teardown]
            public function drop(): void
            {
                $this->dropped = true;
            }
        };

        $subscription = new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Detached);
        $store = new DummySubscriptionStore([$subscription]);

        $handler = $this->createHandler($store, [$subscriber]);
        $result = $handler(new RemoveCommand());

        self::assertEquals([], $result->errors);
        $store->assertNoUpdated();
        $store->assertRemoved($subscription);
        self::assertTrue($subscriber->dropped);
    }

    public function testRemoveWithoutDropMethod(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $subscription = new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Detached);
        $store = new DummySubscriptionStore([$subscription]);

        $handler = $this->createHandler($store, [$subscriber]);
        $result = $handler(new RemoveCommand());

        self::assertEquals([], $result->errors);
        $store->assertNoUpdated();
        $store->assertRemoved($subscription);
    }

    public function testRemoveWithSubscriberAndError(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            #[Teardown]
            public function drop(): void
            {
                throw new RuntimeException('ERROR');
            }
        };

        $subscription = new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Detached);
        $store = new DummySubscriptionStore([$subscription]);

        $handler = $this->createHandler($store, [$subscriber]);
        $result = $handler(new RemoveCommand());

        self::assertCount(1, $result->errors);

        $error = $result->errors[0];
        self::assertEquals($subscriptionId, $error->subscriptionId);
        self::assertEquals('ERROR', $error->message);
        self::assertInstanceOf(RuntimeException::class, $error->throwable);

        $store->assertNoUpdated();
        $store->assertRemoved($subscription);
    }

    public function testRemoveNewSubscriber(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
            public bool $dropped = false;

            #[Teardown]
            public function drop(): void
            {
                $this->dropped = true;
            }
        };

        $subscription = new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::New);
        $store = new DummySubscriptionStore([$subscription]);

        $handler = $this->createHandler($store, [$subscriber]);
        $result = $handler(new RemoveCommand());

        self::assertEquals([], $result->errors);
        $store->assertNoUpdated();
        $store->assertRemoved($subscription);
        self::assertFalse($subscriber->dropped);
    }

    public function testRemoveWithoutSubscriber(): void
    {
        $subscriberId = 'test';

        $subscription = new Subscription($subscriberId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Detached);
        $store = new DummySubscriptionStore([$subscription]);

        $handler = $this->createHandler($store);
        $result = $handler(new RemoveCommand());

        self::assertEquals([], $result->errors);
        $store->assertNoUpdated();
        $store->assertRemoved($subscription);
    }

    public function testRemoveWithCleanupAndWithoutCleaner(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $subscription = new Subscription(
            $subscriptionId,
            Subscription::DEFAULT_GROUP,
            RunMode::FromBeginning,
            Status::Detached,
            cleanupTasks: [new DropTableTask('test')],
        );
        $store = new DummySubscriptionStore([$subscription]);

        $handler = $this->createHandler($store, [$subscriber]);

        $this->expectException(CleanerNotConfigured::class);
        $handler(new RemoveCommand());
    }

    public function testRemoveWithCleanupAndSubscriber(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $task = new DropTableTask('test');
        $subscription = new Subscription(
            $subscriptionId,
            Subscription::DEFAULT_GROUP,
            RunMode::FromBeginning,
            Status::Detached,
            cleanupTasks: [$task],
        );
        $store = new DummySubscriptionStore([$subscription]);

        $cleanupHandler = $this->createMock(CleanupTaskHandler::class);
        $cleanupHandler->expects($this->once())->method('supports')->with($task)->willReturn(true);
        $cleanupHandler->expects($this->once())->method('__invoke')->with($task);

        $subscriptionManager = new SubscriptionManager($store);
        $handler = new RemoveHandler(
            $subscriptionManager,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            new CleanupRunner($subscriptionManager, new DefaultCleaner([$cleanupHandler]), new NullLogger()),
            new EventDispatcher(),
            new NullLogger(),
        );

        $result = $handler(new RemoveCommand());

        self::assertEquals([], $result->errors);
        $store->assertNoUpdated();
        $store->assertRemoved($subscription);
    }

    public function testRemoveWithCleanupHandlerError(): void
    {
        $subscriptionId = 'test';

        $task = new DropTableTask('test');
        $subscription = new Subscription(
            $subscriptionId,
            Subscription::DEFAULT_GROUP,
            RunMode::FromBeginning,
            Status::Detached,
            cleanupTasks: [$task],
        );
        $store = new DummySubscriptionStore([$subscription]);

        $cleanupHandler = $this->createMock(CleanupTaskHandler::class);
        $cleanupHandler->expects($this->once())->method('supports')->with($task)->willReturn(true);
        $cleanupHandler->expects($this->once())->method('__invoke')->with($task)->willThrowException(new RuntimeException('ERROR'));

        $subscriptionManager = new SubscriptionManager($store);
        $handler = new RemoveHandler(
            $subscriptionManager,
            new MetadataSubscriberAccessorRepository([]),
            new CleanupRunner($subscriptionManager, new DefaultCleaner([$cleanupHandler]), new NullLogger()),
            new EventDispatcher(),
            new NullLogger(),
        );

        $result = $handler(new RemoveCommand());

        self::assertCount(1, $result->errors);

        $error = $result->errors[0];
        self::assertEquals($subscriptionId, $error->subscriptionId);
        self::assertInstanceOf(CleanupFailed::class, $error->throwable);

        $store->assertRemoved($subscription);
    }
}
