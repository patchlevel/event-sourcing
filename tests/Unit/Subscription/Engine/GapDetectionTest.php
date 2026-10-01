<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription\Engine;

use DateInterval;
use DateTimeImmutable;
use Patchlevel\EventSourcing\Aggregate\AggregateHeader;
use Patchlevel\EventSourcing\Clock\FrozenClock;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\Header\RecordedOnHeader;
use Patchlevel\EventSourcing\Subscription\Engine\GapDetection;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisited;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(GapDetection::class)]
final class GapDetectionTest extends TestCase
{
    public function testWithoutWindowEveryMessageIsContained(): void
    {
        $gapDetection = new GapDetection(
            new FrozenClock(new DateTimeImmutable('2020-01-01 00:00:00')),
            detectionWindow: null,
        );

        $message = Message::create(new ProfileVisited(ProfileId::fromString('1')))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2000-01-01 00:00:00')));

        self::assertTrue($gapDetection->inWindow($message));
    }

    public function testMessageWithoutRecordedOnIsContained(): void
    {
        $gapDetection = new GapDetection(
            new FrozenClock(new DateTimeImmutable('2020-01-01 00:00:00')),
            detectionWindow: new DateInterval('PT5M'),
        );

        $message = Message::create(new ProfileVisited(ProfileId::fromString('1')));

        self::assertTrue($gapDetection->inWindow($message));
    }

    public function testRecordedOnHeaderInsideWindow(): void
    {
        $gapDetection = new GapDetection(
            new FrozenClock(new DateTimeImmutable('2020-01-01 00:05:00')),
            detectionWindow: new DateInterval('PT5M'),
        );

        $message = Message::create(new ProfileVisited(ProfileId::fromString('1')))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:01')));

        self::assertTrue($gapDetection->inWindow($message));
    }

    public function testRecordedOnHeaderAtEdgeOfWindowIsNotContained(): void
    {
        $gapDetection = new GapDetection(
            new FrozenClock(new DateTimeImmutable('2020-01-01 00:05:00')),
            detectionWindow: new DateInterval('PT5M'),
        );

        $message = Message::create(new ProfileVisited(ProfileId::fromString('1')))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')));

        self::assertFalse($gapDetection->inWindow($message));
    }

    public function testRecordedOnHeaderOutsideWindow(): void
    {
        $gapDetection = new GapDetection(
            new FrozenClock(new DateTimeImmutable('2020-01-01 00:05:00')),
            detectionWindow: new DateInterval('PT5M'),
        );

        $message = Message::create(new ProfileVisited(ProfileId::fromString('1')))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2019-12-31 23:59:59')));

        self::assertFalse($gapDetection->inWindow($message));
    }

    public function testAggregateHeaderInsideWindow(): void
    {
        $gapDetection = new GapDetection(
            new FrozenClock(new DateTimeImmutable('2020-01-01 00:05:00')),
            detectionWindow: new DateInterval('PT5M'),
        );

        $message = Message::create(new ProfileVisited(ProfileId::fromString('1')))
            ->withHeader(new AggregateHeader('profile', '1', 1, new DateTimeImmutable('2020-01-01 00:00:01')));

        self::assertTrue($gapDetection->inWindow($message));
    }

    public function testAggregateHeaderOutsideWindow(): void
    {
        $gapDetection = new GapDetection(
            new FrozenClock(new DateTimeImmutable('2020-01-01 00:05:00')),
            detectionWindow: new DateInterval('PT5M'),
        );

        $message = Message::create(new ProfileVisited(ProfileId::fromString('1')))
            ->withHeader(new AggregateHeader('profile', '1', 1, new DateTimeImmutable('2020-01-01 00:00:00')));

        self::assertFalse($gapDetection->inWindow($message));
    }

    public function testRecordedOnHeaderHasPrecedenceOverAggregateHeader(): void
    {
        $gapDetection = new GapDetection(
            new FrozenClock(new DateTimeImmutable('2020-01-01 00:05:00')),
            detectionWindow: new DateInterval('PT5M'),
        );

        $message = Message::create(new ProfileVisited(ProfileId::fromString('1')))
            ->withHeader(new AggregateHeader('profile', '1', 1, new DateTimeImmutable('2020-01-01 00:04:00')))
            ->withHeader(new RecordedOnHeader(new DateTimeImmutable('2020-01-01 00:00:00')));

        self::assertFalse($gapDetection->inWindow($message));
    }

    public function testDefaultRetries(): void
    {
        $gapDetection = new GapDetection();

        self::assertSame(0, $gapDetection->retry(0));
        self::assertSame(5, $gapDetection->retry(1));
        self::assertSame(50, $gapDetection->retry(2));
        self::assertSame(500, $gapDetection->retry(3));
        self::assertNull($gapDetection->retry(4));
    }

    public function testCustomRetries(): void
    {
        $gapDetection = new GapDetection(
            new FrozenClock(new DateTimeImmutable('2020-01-01 00:00:00')),
            [10, 20],
        );

        self::assertSame(10, $gapDetection->retry(0));
        self::assertSame(20, $gapDetection->retry(1));
        self::assertNull($gapDetection->retry(2));
    }

    public function testWithoutRetries(): void
    {
        $gapDetection = new GapDetection(
            new FrozenClock(new DateTimeImmutable('2020-01-01 00:00:00')),
            [],
        );

        self::assertNull($gapDetection->retry(0));
    }
}
