<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Clock;

use DateTimeImmutable;

use function sprintf;

final class FreezeClock implements Clock
{
    public function __construct(private DateTimeImmutable $frozenDateTime)
    {
    }

    public function update(DateTimeImmutable $frozenDateTime): void
    {
        $this->frozenDateTime = $frozenDateTime;
    }

    /**
     * @param positive-int $seconds
     */
    public function sleep(int $seconds): void
    {
        $this->frozenDateTime = $this->frozenDateTime->modify(sprintf('+%s seconds', $seconds));
    }

    public function create(): DateTimeImmutable
    {
        return $this->frozenDateTime;
    }
}
