<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription;

use Patchlevel\EventSourcing\Subscription\NoErrorToRetry;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Subscription\SubscriptionError;
use Patchlevel\EventSourcing\Subscription\ThrowableToErrorContextTransformer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/** @covers \Patchlevel\EventSourcing\Subscription\Subscription */
final class SubscriptionTest extends TestCase
{
    public function testCreate(): void
    {
        $id = 'test';
        $subscription = new Subscription($id);

        self::assertSame($id, $subscription->id());
        self::assertEquals(Status::New, $subscription->status());
        self::assertEquals(0, $subscription->position());
        self::assertTrue($subscription->isNew());
        self::assertFalse($subscription->isBooting());
        self::assertFalse($subscription->isActive());
        self::assertFalse($subscription->isError());
        self::assertFalse($subscription->isOutdated());
    }

    public function testBooting(): void
    {
        $subscription = new Subscription(
            'test',
        );

        $subscription->booting();

        self::assertEquals(Status::Booting, $subscription->status());
        self::assertFalse($subscription->isNew());
        self::assertTrue($subscription->isBooting());
        self::assertFalse($subscription->isActive());
        self::assertFalse($subscription->isError());
        self::assertFalse($subscription->isOutdated());
    }

    public function testActive(): void
    {
        $subscription = new Subscription(
            'test',
        );

        $subscription->active();

        self::assertEquals(Status::Active, $subscription->status());
        self::assertFalse($subscription->isNew());
        self::assertFalse($subscription->isBooting());
        self::assertTrue($subscription->isActive());
        self::assertFalse($subscription->isError());
        self::assertFalse($subscription->isOutdated());
    }

    public function testError(): void
    {
        $subscription = new Subscription(
            'test',
        );

        $exception = new RuntimeException('test');

        $subscription->error($exception);

        self::assertEquals(Status::Error, $subscription->status());
        self::assertFalse($subscription->isNew());
        self::assertFalse($subscription->isBooting());
        self::assertFalse($subscription->isActive());
        self::assertTrue($subscription->isError());
        self::assertFalse($subscription->isOutdated());
        self::assertEquals(
            new SubscriptionError(
                'test',
                Status::New,
                ThrowableToErrorContextTransformer::transform($exception),
            ),
            $subscription->subscriptionError(),
        );
    }

    public function testOutdated(): void
    {
        $subscription = new Subscription(
            'test',
        );

        $subscription->outdated();

        self::assertEquals(Status::Outdated, $subscription->status());
        self::assertFalse($subscription->isNew());
        self::assertFalse($subscription->isBooting());
        self::assertFalse($subscription->isActive());
        self::assertFalse($subscription->isError());
        self::assertTrue($subscription->isOutdated());
    }

    public function testChangePosition(): void
    {
        $subscription = new Subscription(
            'test',
        );

        $subscription->changePosition(10);

        self::assertEquals(10, $subscription->position());
    }

    public function testCanNotRetry(): void
    {
        $this->expectException(NoErrorToRetry::class);

        $subscription = new Subscription(
            'test',
        );

        $subscription->doRetry();
    }

    public function testDoRetry(): void
    {
        $subscription = new Subscription(
            'test',
            'default',
            RunMode::FromBeginning,
            Status::Error,
            0,
            new SubscriptionError('test', Status::New, []),
        );

        self::assertEquals(null, $subscription->retryAttempt());
        $subscription->doRetry();

        self::assertEquals(1, $subscription->retryAttempt());
        $subscription->resetRetry();

        self::assertEquals(null, $subscription->retryAttempt());
    }
}
