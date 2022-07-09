<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Clock;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Clock\SystemClock;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Clock\SystemClock */
final class SystemClockTest extends TestCase
{
    public function testCreateDateTimeImmutable(): void
    {
        $before = new DateTimeImmutable();
        $date = (new SystemClock())->now();
        $after = new DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $date);
        self::assertLessThanOrEqual($after, $date);
    }
}
