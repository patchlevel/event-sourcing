<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\RetryStrategy;

use DateTimeImmutable;
use Patchlevel\EventSourcing\Subscription\Subscription;

interface DelayRetryStrategy extends ConditionalRetryStrategy
{
    public function delayUntil(Subscription $subscription): DateTimeImmutable;
}
