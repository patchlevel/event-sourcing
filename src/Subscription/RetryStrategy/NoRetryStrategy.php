<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\RetryStrategy;

use Patchlevel\EventSourcing\Subscription\Subscription;

final class NoRetryStrategy implements ConditionalRetryStrategy
{
    public function shouldRetry(Subscription $subscription): bool
    {
        return false;
    }

    public function canRetry(Subscription $subscription): bool
    {
        return false;
    }
}
