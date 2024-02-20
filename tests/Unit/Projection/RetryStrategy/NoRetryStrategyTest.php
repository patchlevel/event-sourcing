<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\RetryStrategy;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Projection\RetryStrategy\NoRetryStrategy;
use Patchlevel\EventSourcing\Projection\RetryStrategy\Retry;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Projection\RetryStrategy\NoRetryStrategy */
final class NoRetryStrategyTest extends TestCase
{
    public function testShouldRetryWithNull(): void
    {
        $strategy = new NoRetryStrategy();
        self::assertFalse($strategy->shouldRetry(null));
    }

    public function testShouldRetryWithoutTime(): void
    {
        $strategy = new NoRetryStrategy();
        self::assertFalse($strategy->shouldRetry(new Retry(1, null)));
    }

    public function testShouldRetryWithTime(): void
    {
        $strategy = new NoRetryStrategy();
        self::assertFalse($strategy->shouldRetry(new Retry(1, new DateTimeImmutable())));
    }

    public function testNextAttemptWithNull(): void
    {
        $strategy = new NoRetryStrategy();
        self::assertNull($strategy->nextAttempt(null));
    }

    public function testNextAttemptWithoutTime(): void
    {
        $strategy = new NoRetryStrategy();
        self::assertNull($strategy->nextAttempt(
            new Retry(1, null),
        ));
    }

    public function testNextAttemptWithTime(): void
    {
        $strategy = new NoRetryStrategy();
        self::assertNull($strategy->nextAttempt(
            new Retry(1, new DateTimeImmutable()),
        ));
    }
}
