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
use Patchlevel\EventSourcing\Subscription\Engine\Command\Teardown as TeardownCommand;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnSubscriptionRemoved;
use Patchlevel\EventSourcing\Subscription\Engine\Handler\TeardownHandler;
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

#[CoversClass(TeardownHandler::class)]
final class TeardownHandlerTest extends TestCase
{
    /** @var list<Subscription> */
    private array $removedSubscriptions = [];

    protected function setUp(): void
    {
        $this->removedSubscriptions = [];
    }

    private function recordingDispatcher(): EventDispatcher
    {
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            OnSubscriptionRemoved::class,
            function (OnSubscriptionRemoved $event): void {
                $this->removedSubscriptions[] = $event->subscription;
            },
        );

        return $dispatcher;
    }

    /** @param list<object> $subscribers */
    private function createHandler(
        DummySubscriptionStore $store,
        array $subscribers = [],
        CleanupRunner|null $cleanupRunner = null,
    ): TeardownHandler {
        $subscriptionManager = new SubscriptionManager($store);

        return new TeardownHandler(
            $subscriptionManager,
            new MetadataSubscriberAccessorRepository($subscribers),
            $cleanupRunner ?? new CleanupRunner($subscriptionManager, null, new NullLogger()),
            $this->recordingDispatcher(),
            new NullLogger(),
        );
    }

    public function testTeardownWithoutTeardownMethod(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $subscription = new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Detached);
        $store = new DummySubscriptionStore([$subscription]);

        $handler = $this->createHandler($store, [$subscriber]);
        $result = $handler(new TeardownCommand());

        self::assertEquals([], $result->errors);
        $store->assertNoUpdated();
        $store->assertRemoved($subscription);
        self::assertSame([$subscription], $this->removedSubscriptions);
    }

    public function testTeardownWithSubscriber(): void
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
        $result = $handler(new TeardownCommand());

        self::assertEquals([], $result->errors);
        $store->assertNoUpdated();
        $store->assertRemoved($subscription);
        self::assertTrue($subscriber->dropped);
        self::assertSame([$subscription], $this->removedSubscriptions);
    }

    public function testTeardownWithSubscriberAndError(): void
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

        $store = new DummySubscriptionStore([
            new Subscription($subscriptionId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Detached),
        ]);

        $handler = $this->createHandler($store, [$subscriber]);
        $result = $handler(new TeardownCommand());

        self::assertCount(1, $result->errors);

        $error = $result->errors[0];
        self::assertEquals($subscriptionId, $error->subscriptionId);
        self::assertEquals('ERROR', $error->message);
        self::assertInstanceOf(RuntimeException::class, $error->throwable);

        $store->assertNoChanges();
        self::assertSame([], $this->removedSubscriptions);
    }

    public function testTeardownWithoutSubscriber(): void
    {
        $subscriberId = 'test';

        $store = new DummySubscriptionStore([
            new Subscription($subscriberId, Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Detached),
        ]);

        $handler = $this->createHandler($store);
        $result = $handler(new TeardownCommand());

        self::assertEquals([], $result->errors);
        $store->assertNoChanges();
        self::assertSame([], $this->removedSubscriptions);
    }

    public function testTeardownWithCleanupAndWithoutCleaner(): void
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
        $handler(new TeardownCommand());
    }

    public function testTeardownWithCleanupAndSubscriber(): void
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
        $handler = new TeardownHandler(
            $subscriptionManager,
            new MetadataSubscriberAccessorRepository([$subscriber]),
            new CleanupRunner($subscriptionManager, new DefaultCleaner([$cleanupHandler]), new NullLogger()),
            $this->recordingDispatcher(),
            new NullLogger(),
        );

        $result = $handler(new TeardownCommand());

        self::assertEquals([], $result->errors);
        $store->assertNoUpdated();
        $store->assertRemoved($subscription);
        self::assertSame([$subscription], $this->removedSubscriptions);
    }

    public function testTeardownWithCleanupAndWithoutSubscriber(): void
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
        $cleanupHandler->expects($this->once())->method('__invoke')->with($task);

        $subscriptionManager = new SubscriptionManager($store);
        $handler = new TeardownHandler(
            $subscriptionManager,
            new MetadataSubscriberAccessorRepository([]),
            new CleanupRunner($subscriptionManager, new DefaultCleaner([$cleanupHandler]), new NullLogger()),
            $this->recordingDispatcher(),
            new NullLogger(),
        );

        $result = $handler(new TeardownCommand());

        self::assertEquals([], $result->errors);
        $store->assertNoUpdated();
        $store->assertRemoved($subscription);
        self::assertSame([$subscription], $this->removedSubscriptions);
    }

    public function testTeardownWithCleanupHandlerError(): void
    {
        $subscriptionId = 'test';

        $task = new DropTableTask('test');
        $store = new DummySubscriptionStore([
            new Subscription(
                $subscriptionId,
                Subscription::DEFAULT_GROUP,
                RunMode::FromBeginning,
                Status::Detached,
                cleanupTasks: [$task],
            ),
        ]);

        $cleanupHandler = $this->createMock(CleanupTaskHandler::class);
        $cleanupHandler->expects($this->once())->method('supports')->with($task)->willReturn(true);
        $cleanupHandler->expects($this->once())->method('__invoke')->with($task)->willThrowException(new RuntimeException('ERROR'));

        $subscriptionManager = new SubscriptionManager($store);
        $handler = new TeardownHandler(
            $subscriptionManager,
            new MetadataSubscriberAccessorRepository([]),
            new CleanupRunner($subscriptionManager, new DefaultCleaner([$cleanupHandler]), new NullLogger()),
            $this->recordingDispatcher(),
            new NullLogger(),
        );

        $result = $handler(new TeardownCommand());

        self::assertCount(1, $result->errors);

        $error = $result->errors[0];
        self::assertEquals($subscriptionId, $error->subscriptionId);
        self::assertInstanceOf(CleanupFailed::class, $error->throwable);

        $store->assertNoChanges();
        self::assertSame([], $this->removedSubscriptions);
    }

    public function testTeardownContinuesWithNextSubscriptionAfterCleanupError(): void
    {
        $failingTask = new DropTableTask('failing');
        $passingTask = new DropTableTask('passing');

        $failing = new Subscription('failing', Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Detached, cleanupTasks: [$failingTask]);
        $passing = new Subscription('passing', Subscription::DEFAULT_GROUP, RunMode::FromBeginning, Status::Detached, cleanupTasks: [$passingTask]);
        $store = new DummySubscriptionStore([$failing, $passing]);

        $cleanupHandler = $this->createMock(CleanupTaskHandler::class);
        $cleanupHandler->method('supports')->willReturn(true);
        $cleanupHandler->method('__invoke')->willReturnCallback(
            static function (object $task) use ($failingTask): void {
                if ($task === $failingTask) {
                    throw new RuntimeException('ERROR');
                }
            },
        );

        $subscriptionManager = new SubscriptionManager($store);
        $handler = new TeardownHandler(
            $subscriptionManager,
            new MetadataSubscriberAccessorRepository([]),
            new CleanupRunner($subscriptionManager, new DefaultCleaner([$cleanupHandler]), new NullLogger()),
            $this->recordingDispatcher(),
            new NullLogger(),
        );

        $result = $handler(new TeardownCommand());

        self::assertCount(1, $result->errors);
        // the second subscription is still processed after the first one failed
        $store->assertRemoved($passing);
        self::assertSame([$passing], $this->removedSubscriptions);
    }
}
