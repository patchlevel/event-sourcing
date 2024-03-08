<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Store;

use Closure;

interface LockableSubscriptionStore extends SubscriptionStore
{
    public function inLock(Closure $closure): void;
}
