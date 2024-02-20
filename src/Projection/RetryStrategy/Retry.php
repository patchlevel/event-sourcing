<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\RetryStrategy;

use DateTimeImmutable;

final class Retry
{
    public function __construct(
        public readonly int $attempt,
        public readonly DateTimeImmutable|null $nextRetry = null,
    ) {
    }

    public function attempt(): int
    {
        return $this->attempt;
    }

    public function nextRetry(): DateTimeImmutable|null
    {
        return $this->nextRetry;
    }

    public function canRetry(): bool
    {
        return $this->nextRetry !== null;
    }
}
