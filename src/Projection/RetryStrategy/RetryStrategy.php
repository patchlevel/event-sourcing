<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\RetryStrategy;

interface RetryStrategy
{
    public function nextAttempt(Retry|null $retry): Retry|null;

    public function shouldRetry(Retry|null $retry): bool;
}
