<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine\Listener;

use Generator;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Boot;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Run;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Setup;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnCommand;
use Patchlevel\EventSourcing\Subscription\Engine\Listener\RetrySubscriber;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\RetryStrategy;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\RetryStrategyRepository;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Subscription\SubscriptionError;
use Patchlevel\EventSourcing\Tests\Unit\Subscription\DummySubscriptionStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;

#[CoversClass(RetrySubscriber::class)]
final class RetrySubscriberTest extends TestCase
{
    private function createListener(
        DummySubscriptionStore $store,
        array $subscribers,
        RetryStrategy $retryStrategy,
    ): RetrySubscriber {
        return new RetrySubscriber(
            new SubscriptionManager($store),
            new MetadataSubscriberAccessorRepository($subscribers),
            RetryStrategyRepository::withDefault($retryStrategy),
            new NullLogger(),
        );
    }

    public function testRetry(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $subscription = new Subscription(
            $subscriptionId,
            Subscription::DEFAULT_GROUP,
            RunMode::FromBeginning,
            Status::Error,
            0,
            new SubscriptionError('ERROR', Status::Active),
        );

        $store = new DummySubscriptionStore([$subscription]);

        $retryStrategy = $this->createMock(RetryStrategy::class);
        $retryStrategy->method('shouldRetry')->with($subscription)->willReturn(true);

        $listener = $this->createListener($store, [$subscriber], $retryStrategy);
        $listener->onCommand(new OnCommand(new Run()));

        self::assertCount(1, $store->updatedSubscriptions);

        $updated = $store->updatedSubscriptions[0];
        self::assertEquals($subscriptionId, $updated->id());
        self::assertEquals(Status::Active, $updated->status());
        self::assertEquals(1, $updated->retryAttempt());
        self::assertNull($updated->subscriptionError());
    }

    #[DataProvider('statusProvider')]
    public function testShouldNotRetryOtherStatus(string $method, string $status): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $subscription = new Subscription(
            $subscriptionId,
            Subscription::DEFAULT_GROUP,
            RunMode::FromBeginning,
            Status::Error,
            0,
            new SubscriptionError('ERROR', Status::from($status)),
        );

        $store = new DummySubscriptionStore([$subscription]);

        $retryStrategy = $this->createMock(RetryStrategy::class);
        $retryStrategy->expects($this->never())->method('shouldRetry');

        $listener = $this->createListener($store, [$subscriber], $retryStrategy);

        $command = match ($method) {
            'setup' => new Setup(),
            'boot' => new Boot(),
            'run' => new Run(),
        };

        $listener->onCommand(new OnCommand($command));

        $store->assertNoChanges();
    }

    public static function statusProvider(): Generator
    {
        yield 'setup_booting' => ['setup', 'booting'];
        yield 'setup_active' => ['setup', 'active'];
        yield 'boot_new' => ['boot', 'new'];
        yield 'boot_active' => ['boot', 'active'];
        yield 'run_new' => ['run', 'new'];
        yield 'run_booting' => ['run', 'booting'];
    }

    public function testShouldNotRetry(): void
    {
        $subscriptionId = 'test';
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $subscription = new Subscription(
            $subscriptionId,
            Subscription::DEFAULT_GROUP,
            RunMode::FromBeginning,
            Status::Error,
            0,
            new SubscriptionError('ERROR', Status::Active),
        );

        $store = new DummySubscriptionStore([$subscription]);

        $retryStrategy = $this->createMock(RetryStrategy::class);
        $retryStrategy->method('shouldRetry')->with($subscription)->willReturn(false);

        $listener = $this->createListener($store, [$subscriber], $retryStrategy);
        $listener->onCommand(new OnCommand(new Run()));

        $store->assertNoChanges();
    }
}
