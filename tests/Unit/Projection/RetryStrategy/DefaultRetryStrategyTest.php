<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\RetryStrategy;

use DateTimeImmutable;
use Generator;
use Patchlevel\EventSourcing\Clock\FrozenClock;
use Patchlevel\EventSourcing\Projection\RetryStrategy\DefaultRetryStrategy;
use Patchlevel\EventSourcing\Projection\RetryStrategy\Retry;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

/** @covers \Patchlevel\EventSourcing\Projection\RetryStrategy\DefaultRetryStrategy */
final class DefaultRetryStrategyTest extends TestCase
{
    private DefaultRetryStrategy $strategy;

    private ClockInterface $clock;

    public function setUp(): void
    {
        $this->clock = new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00+00:00'));
        $this->strategy = new DefaultRetryStrategy($this->clock);
    }

    public function testShouldRetryWithNull(): void
    {
        self::assertFalse($this->strategy->shouldRetry(null));
    }

    public function testShouldRetryWithoutTime(): void
    {
        self::assertFalse($this->strategy->shouldRetry(new Retry(1, null)));
    }

    public function testShouldRetryWithTime(): void
    {
        self::assertFalse($this->strategy->shouldRetry(new Retry(1, new DateTimeImmutable())));
    }

    public function testNextAttemptWithNull(): void
    {
        $expected = new Retry(1, new DateTimeImmutable('2021-01-01T00:00:10+00:00'));

        self::assertEquals($expected, $this->strategy->nextAttempt(null));
    }

    #[DataProvider('attemptProvider')]
    public function testNextAttempt(int $attempt, string $dateString): void
    {
        $expected = new Retry($attempt, new DateTimeImmutable($dateString));

        self::assertEquals(
            $expected,
            $this->strategy->nextAttempt(
                new Retry(
                    $attempt - 1,
                    null,
                ),
            ),
        );
    }

    public static function attemptProvider(): Generator
    {
        yield 'first attempt' => [1, '2021-01-01T00:00:5+00:00'];
        yield 'second attempt' => [2, '2021-01-01T00:00:10+00:00'];
        yield 'third attempt' => [3, '2021-01-01T00:00:20+00:00'];
        yield 'fourth attempt' => [4, '2021-01-01T00:00:40+00:00'];
        yield 'fifth attempt' => [5, '2021-01-01T00:01:20+00:00'];
    }

    public function testMaxAttempt(): void
    {
        self::assertNull(
            $this->strategy->nextAttempt(
                new Retry(
                    6,
                    null,
                ),
            ),
        );
    }
}
