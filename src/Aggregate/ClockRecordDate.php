<?php

namespace Patchlevel\EventSourcing\Aggregate;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Clock;

/**
 * @psalm-require-extends AggregateChanged
 */
trait ClockRecordDate
{
    protected function createRecordDate(): DateTimeImmutable
    {
        return Clock::createDateTimeImmutable();
    }
}