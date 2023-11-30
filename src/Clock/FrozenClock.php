<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Clock;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

use function sprintf;

final class FrozenClock implements ClockInterface
{
    public function __construct(
        private DateTimeImmutable $frozenDateTime,
    ) {
    }

    public function now(): DateTimeImmutable
    {
        return clone $this->frozenDateTime;
    }

    public function update(DateTimeImmutable $frozenDateTime): void
    {
        $this->frozenDateTime = $frozenDateTime;
    }

    /** @param positive-int $seconds */
    public function sleep(int $seconds): void
    {
        $this->frozenDateTime = $this->frozenDateTime->modify(sprintf('+%s seconds', $seconds));
    }
}
