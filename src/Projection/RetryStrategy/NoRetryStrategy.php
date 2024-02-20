<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\RetryStrategy;

final class NoRetryStrategy implements RetryStrategy
{
    public function nextAttempt(Retry|null $retry): Retry|null
    {
        return null;
    }

    public function shouldRetry(Retry|null $retry): bool
    {
        return false;
    }
}
