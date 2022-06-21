<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Clock;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Clock\FrozenClock;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Clock\FrozenClock */
class FrozenClockTest extends TestCase
{
    public function testCreateDateTimeImmutableWithFrozenClock(): void
    {
        $current = new DateTimeImmutable();
        $clock = new FrozenClock($current);

        $new = $clock->now();

        self::assertEquals($current, $new);
        self::assertNotSame($current, $new);
    }

    public function testSleep(): void
    {
        $date1 = new DateTimeImmutable();
        $clock = new FrozenClock($date1);
        $clock->sleep(1);
        $date2 = $clock->now();

        $diff = $date1->diff($date2);

        self::assertSame(1, $diff->s);
    }

    public function testReFreeze(): void
    {
        $date1 = new DateTimeImmutable();
        $clock = new FrozenClock($date1);
        $new1 = $clock->now();

        $date2 = new DateTimeImmutable();
        $clock->update($date2);
        $new2 = $clock->now();

        self::assertEquals($date1, $new1);
        self::assertEquals($date2, $new2);
        self::assertNotEquals($new1, $new2);
    }
}
