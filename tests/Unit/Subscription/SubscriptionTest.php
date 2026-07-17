<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Subscription\NoErrorToRetry;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Patchlevel\EventSourcing\Subscription\SubscriptionError;
use Patchlevel\EventSourcing\Subscription\ThrowableToErrorContextTransformer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

#[CoversClass(Subscription::class)]
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
        self::assertFalse($subscription->isDetached());
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
        self::assertFalse($subscription->isDetached());
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
        self::assertFalse($subscription->isDetached());
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
        self::assertFalse($subscription->isDetached());
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

        $subscription->detached();

        self::assertEquals(Status::Detached, $subscription->status());
        self::assertFalse($subscription->isNew());
        self::assertFalse($subscription->isBooting());
        self::assertFalse($subscription->isActive());
        self::assertFalse($subscription->isError());
        self::assertTrue($subscription->isDetached());
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

    public function testGroupAndRunMode(): void
    {
        $subscription = new Subscription('foo');

        self::assertSame('foo', $subscription->subscriberId());
        self::assertSame(Subscription::DEFAULT_GROUP, $subscription->group());
        self::assertSame(RunMode::FromBeginning, $subscription->runMode());

        $subscription->changeGroup('other');
        $subscription->changeRunMode(RunMode::Once);

        self::assertSame('other', $subscription->group());
        self::assertSame(RunMode::Once, $subscription->runMode());
    }

    public function testNew(): void
    {
        $subscription = new Subscription('foo', status: Status::Error);

        $subscription->new();

        self::assertTrue($subscription->isNew());
        self::assertNull($subscription->subscriptionError());
    }

    public function testPause(): void
    {
        $subscription = new Subscription('foo');

        $subscription->pause();

        self::assertTrue($subscription->isPaused());
        self::assertSame(Status::Paused, $subscription->status());
    }

    public function testFinished(): void
    {
        $subscription = new Subscription('foo');

        $subscription->finished();

        self::assertTrue($subscription->isFinished());
        self::assertNull($subscription->subscriptionError());
    }

    public function testErrorWithString(): void
    {
        $subscription = new Subscription('foo', status: Status::Active);

        $subscription->error('something went wrong');

        self::assertTrue($subscription->isError());

        $error = $subscription->subscriptionError();

        self::assertNotNull($error);
        self::assertSame('something went wrong', $error->errorMessage);
        self::assertSame(Status::Active, $error->previousStatus);
    }

    public function testFailedWithThrowable(): void
    {
        $subscription = new Subscription('foo', status: Status::Active);

        $subscription->failed(new RuntimeException('something went wrong'));

        self::assertTrue($subscription->isFailed());

        $error = $subscription->subscriptionError();

        self::assertNotNull($error);
        self::assertSame('something went wrong', $error->errorMessage);
        self::assertSame(Status::Active, $error->previousStatus);
    }

    public function testFailedWithString(): void
    {
        $subscription = new Subscription('foo', status: Status::Active);

        $subscription->failed('something went wrong');

        self::assertTrue($subscription->isFailed());

        $error = $subscription->subscriptionError();

        self::assertNotNull($error);
        self::assertSame('something went wrong', $error->errorMessage);
    }

    public function testLastSavedAt(): void
    {
        $subscription = new Subscription('foo');

        self::assertNull($subscription->lastSavedAt());

        $now = new DateTimeImmutable();
        $subscription->updateLastSavedAt($now);

        self::assertSame($now, $subscription->lastSavedAt());
    }

    public function testCleanupTasks(): void
    {
        $subscription = new Subscription('foo');

        self::assertNull($subscription->cleanupTasks());
        self::assertFalse($subscription->hasCleanupTasks());

        $task = new stdClass();
        $subscription->replaceCleanupTasks([$task]);

        self::assertSame([$task], $subscription->cleanupTasks());
        self::assertTrue($subscription->hasCleanupTasks());
    }

}
