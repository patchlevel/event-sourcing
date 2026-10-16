<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine\Listener;

use Generator;
use Patchlevel\EventSourcing\Attribute\Partition;
use Patchlevel\EventSourcing\Attribute\Subscriber;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Boot;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Run;
use Patchlevel\EventSourcing\Subscription\Engine\Command\Setup;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnCommand;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnHandleMessageSuccess;
use Patchlevel\EventSourcing\Subscription\Engine\Listener\RetrySubscriber;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\Partitioner\StreamPartitioner;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\RetryStrategy;
use Patchlevel\EventSourcing\Subscription\RetryStrategy\RetryStrategyRepository;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Subscription\SubscriptionError;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use Patchlevel\EventSourcing\Tests\Unit\Subscription\DummySubscriptionStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(RetrySubscriber::class)]
final class RetrySubscriberTest extends TestCase
{
    /** @param list<object> $subscribers */
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

    /** @param 'setup'|'boot'|'run' $method */
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

    public function testOnlyDueSubscriptionsAreClaimed(): void
    {
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        class {
        };

        $due = new Subscription('test', status: Status::Error, position: 0, error: new SubscriptionError('ERROR', Status::Active));
        $waiting = Subscription::partition(new Subscription('test'), 'a', 0);
        $waiting->error('ERROR');

        $store = new DummySubscriptionStore([$due, $waiting]);

        $retryStrategy = $this->createMock(RetryStrategy::class);
        $retryStrategy->method('shouldRetry')->willReturnCallback(
            static fn (Subscription $subscription): bool => $subscription->id() === 'test',
        );

        $listener = $this->createListener($store, [$subscriber], $retryStrategy);
        $listener->onCommand(new OnCommand(new Run()));

        self::assertSame(['test'], $store->claimedIds, 'a subscription whose retry is not due is not claimed');
        self::assertSame(Status::Active, $store->get('test')->status());
        self::assertSame(Status::Error, $store->get('test#a')->status());
    }

    public function testRoutingDoesNotResetTheRetriesOfThePartitionedMain(): void
    {
        $subscriber = new #[Subscriber('test', RunMode::FromBeginning)]
        #[Partition(new StreamPartitioner())]
        class {
        };

        $main = new Subscription('test', status: Status::Active, retryAttempt: 3);
        $partition = new Subscription('test#key', status: Status::Active, retryAttempt: 2, partitionKey: 'key');

        $listener = $this->createListener(new DummySubscriptionStore([$main, $partition]), [$subscriber], $this->createMock(RetryStrategy::class));
        $message = Message::create(new ProfileVisited(ProfileId::fromString('1')));

        $listener->onSuccessHandleMessage(new OnHandleMessageSuccess($main, $message, 1));
        $listener->onSuccessHandleMessage(new OnHandleMessageSuccess($partition, $message, 1));

        self::assertSame(3, $main->retryAttempt(), 'routing works even if the partitions fail');
        self::assertSame(0, $partition->retryAttempt());
    }
}
