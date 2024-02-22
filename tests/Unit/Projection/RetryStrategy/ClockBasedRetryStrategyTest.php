<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection\RetryStrategy;

use DateTimeImmutable;
use Generator;
use Patchlevel\EventSourcing\Clock\FrozenClock;
use Patchlevel\EventSourcing\Projection\Projection\Projection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionStatus;
use Patchlevel\EventSourcing\Projection\Projection\RunMode;
use Patchlevel\EventSourcing\Projection\RetryStrategy\ClockBasedRetryStrategy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Projection\RetryStrategy\ClockBasedRetryStrategy */
final class ClockBasedRetryStrategyTest extends TestCase
{
    private ClockBasedRetryStrategy $strategy;

    private FrozenClock $clock;

    public function setUp(): void
    {
        $this->clock = new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00+00:00'));
        $this->strategy = new ClockBasedRetryStrategy($this->clock);
    }

    /** @param positive-int $seconds */
    #[DataProvider('attemptProvider')]
    public function testShouldRetry(int $attempt, int $seconds, bool $expected): void
    {
        $projection = new Projection(
            'test',
            'default',
            RunMode::FromBeginning,
            ProjectionStatus::Error,
            0,
            null,
            $attempt,
            $this->clock->now(),
        );

        $this->clock->sleep($seconds);

        self::assertEquals(
            $expected,
            $this->strategy->shouldRetry($projection),
        );
    }

    public static function attemptProvider(): Generator
    {
        yield [0, 0, false];
        yield [0, 5, true];
        yield [1, 5, false];
        yield [1, 10, true];
        yield [2, 10, false];
        yield [2, 20, true];
        yield [3, 20, false];
        yield [3, 40, true];
        yield [4, 40, false];
        yield [4, 80, true];
        yield [5, 80, false];
        yield [5, 160, false];
        yield [5, 320, false];
    }
}
