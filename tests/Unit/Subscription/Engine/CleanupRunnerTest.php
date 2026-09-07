<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine;

use Patchlevel\EventSourcing\Subscription\Cleanup\Cleaner;
use Patchlevel\EventSourcing\Subscription\Engine\CleanerNotConfigured;
use Patchlevel\EventSourcing\Subscription\Engine\CleanupRunner;
use Patchlevel\EventSourcing\Subscription\Engine\Error;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Subscription;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(CleanupRunner::class)]
final class CleanupRunnerTest extends TestCase
{
    public function testCleanerNotConfigured(): void
    {
        $subscriptionStore = $this->createMock(SubscriptionStore::class);
        $runner = new CleanupRunner(new SubscriptionManager($subscriptionStore));

        $this->expectException(CleanerNotConfigured::class);

        $runner->cleanup(new Subscription('foo'));
    }

    public function testCleanupSuccessful(): void
    {
        $subscription = new Subscription('foo');

        $subscriptionStore = $this->createMock(SubscriptionStore::class);
        $subscriptionStore
            ->expects($this->once())
            ->method('remove')
            ->with($subscription);

        $cleaner = $this->createMock(Cleaner::class);
        $cleaner
            ->expects($this->once())
            ->method('cleanup')
            ->with($subscription);

        $subscriptionManager = new SubscriptionManager($subscriptionStore);
        $runner = new CleanupRunner($subscriptionManager, $cleaner);

        self::assertNull($runner->cleanup($subscription));

        $subscriptionManager->flush();
    }

    public function testCleanupFailed(): void
    {
        $subscription = new Subscription('foo');

        $subscriptionStore = $this->createMock(SubscriptionStore::class);
        $subscriptionStore
            ->expects($this->never())
            ->method('remove');

        $exception = new RuntimeException('cleanup failed');

        $cleaner = $this->createMock(Cleaner::class);
        $cleaner
            ->expects($this->once())
            ->method('cleanup')
            ->with($subscription)
            ->willThrowException($exception);

        $subscriptionManager = new SubscriptionManager($subscriptionStore);
        $runner = new CleanupRunner($subscriptionManager, $cleaner);

        $error = $runner->cleanup($subscription);

        self::assertInstanceOf(Error::class, $error);
        self::assertSame('foo', $error->subscriptionId);
        self::assertSame('cleanup failed', $error->message);
        self::assertSame($exception, $error->throwable);

        $subscriptionManager->flush();
    }

    public function testCleanupFailedWithForce(): void
    {
        $subscription = new Subscription('foo');

        $subscriptionStore = $this->createMock(SubscriptionStore::class);
        $subscriptionStore
            ->expects($this->once())
            ->method('remove')
            ->with($subscription);

        $cleaner = $this->createMock(Cleaner::class);
        $cleaner
            ->expects($this->once())
            ->method('cleanup')
            ->with($subscription)
            ->willThrowException(new RuntimeException('cleanup failed'));

        $subscriptionManager = new SubscriptionManager($subscriptionStore);
        $runner = new CleanupRunner($subscriptionManager, $cleaner);

        $error = $runner->cleanup($subscription, true);

        self::assertInstanceOf(Error::class, $error);

        $subscriptionManager->flush();
    }
}
