<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Clock;
use PHPUnit\Framework\TestCase;

/** @covers \Patchlevel\EventSourcing\Clock */
class ClockTest extends TestCase
{
    protected function setUp(): void
    {
        Clock::reset();
    }

    protected function tearDown(): void
    {
        Clock::reset();
    }

    public function testCreateDateTimeImmutable(): void
    {
        $before = new DateTimeImmutable();
        $date = Clock::createDateTimeImmutable();
        $after = new DateTimeImmutable();

        self::assertGreaterThanOrEqual($before, $date);
        self::assertLessThanOrEqual($after, $date);
    }

    public function testCreateDateTimeImmutableWithFrozenClock(): void
    {
        $current = new DateTimeImmutable();

        Clock::freeze($current);

        $new = Clock::createDateTimeImmutable();

        self::assertEquals($current, $new);
    }

    public function testReset(): void
    {
        $current = new DateTimeImmutable();
        Clock::freeze($current);
        Clock::reset();

        $before = new DateTimeImmutable();
        $date = Clock::createDateTimeImmutable();
        $after = new DateTimeImmutable();

        self::assertNotEquals($current, $date);
        self::assertGreaterThanOrEqual($before, $date);
        self::assertLessThanOrEqual($after, $date);
    }

    public function testSleep(): void
    {
        $date1 = Clock::createDateTimeImmutable();
        Clock::sleep(1);
        $date2 = Clock::createDateTimeImmutable();

        $diff = $date1->diff($date2);

        self::assertEquals(1, $diff->s);
    }

    public function testSleepWithFrozenClock(): void
    {
        $current = new DateTimeImmutable();
        Clock::freeze($current);

        $date1 = Clock::createDateTimeImmutable();
        Clock::sleep(45);
        $date2 = Clock::createDateTimeImmutable();

        $diff = $date1->diff($date2);

        self::assertEquals(45, $diff->s);
    }
}
