<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\RetryStrategy;

use Patchlevel\EventSourcing\Projection\Projection\Projection;

final class NoRetryStrategy implements RetryStrategy
{
    public function shouldRetry(Projection $projection): bool
    {
        return false;
    }
}
