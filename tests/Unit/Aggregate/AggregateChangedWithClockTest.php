<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Aggregate;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Clock;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileId;
use Patchlevel\EventSourcing\Tests\Unit\Fixture\ProfileVisitedWithClock;
use PHPUnit\Framework\TestCase;

class AggregateChangedWithClockTest extends TestCase
{
    public function testRecordedAt(): void
    {
        $date = new DateTimeImmutable();

        Clock::freeze($date);

        $profile1 = ProfileId::fromString('1');
        $profile2 = ProfileId::fromString('2');

        $event = ProfileVisitedWithClock::raise($profile1, $profile2);
        $event->recordNow(1);

        self::assertSame($date, $event->recordedOn());
    }
}
