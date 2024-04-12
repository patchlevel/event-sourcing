<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Store;

use Closure;

interface LockableSubscriptionStore extends SubscriptionStore
{
    /**
     * @param Closure():T $closure
     *
     * @return T
     *
     * @throws TransactionCommitNotPossible
     *
     * @template T
     */
    public function inLock(Closure $closure): mixed;
}
