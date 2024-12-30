<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\RetryStrategy;

use Patchlevel\EventSourcing\Subscription\Subscription;

interface ConditionalRetryStrategy extends RetryStrategy
{
    public function canRetry(Subscription $subscription): bool;
}
