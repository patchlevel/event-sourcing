<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\RetryStrategy;

use Patchlevel\EventSourcing\Subscription\Subscription;

interface RetryStrategy
{
    public function shouldRetry(Subscription $subscription): bool;
}
