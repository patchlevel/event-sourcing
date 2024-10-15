<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Closure;
use Patchlevel\EventSourcing\Subscription\Store\LockableSubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Subscription;
use SplObjectStorage;

/** @internal */
final class SubscriptionManager
{
    /** @var SplObjectStorage<Subscription, null> */
    private SplObjectStorage $forAdd;

    /** @var SplObjectStorage<Subscription, null> */
    private SplObjectStorage $forUpdate;

    /** @var SplObjectStorage<Subscription, null> */
    private SplObjectStorage $forRemove;

    public function __construct(
        private readonly SubscriptionStore $subscriptionStore,
    ) {
        $this->forAdd = new SplObjectStorage();
        $this->forUpdate = new SplObjectStorage();
        $this->forRemove = new SplObjectStorage();
    }

    /**
     * @param Closure(list<Subscription>):T $closure
     *
     * @return T
     *
     * @template T
     */
    public function findForUpdate(SubscriptionCriteria $criteria, Closure $closure): mixed
    {
        if (!$this->subscriptionStore instanceof LockableSubscriptionStore) {
            try {
                return $closure($this->subscriptionStore->find($criteria));
            } finally {
                $this->flush();
            }
        }

        return $this->subscriptionStore->inLock(
        /** @return T */
            function () use ($closure, $criteria): mixed {
                try {
                    return $closure($this->subscriptionStore->find($criteria));
                } finally {
                    $this->flush();
                }
            },
        );
    }

    /** @return list<Subscription> */
    public function find(SubscriptionCriteria $criteria): array
    {
        return $this->subscriptionStore->find($criteria);
    }

    public function add(Subscription ...$subscriptions): void
    {
        foreach ($subscriptions as $sub) {
            $this->forAdd->attach($sub);
        }
    }

    public function update(Subscription ...$subscriptions): void
    {
        foreach ($subscriptions as $sub) {
            $this->forUpdate->attach($sub);
        }
    }

    public function remove(Subscription ...$subscriptions): void
    {
        foreach ($subscriptions as $sub) {
            $this->forRemove->attach($sub);
        }
    }

    public function flush(): void
    {
        foreach ($this->forAdd as $subscription) {
            if ($this->forRemove->contains($subscription)) {
                continue;
            }

            $this->subscriptionStore->add($subscription);
        }

        foreach ($this->forUpdate as $subscription) {
            if ($this->forAdd->contains($subscription)) {
                continue;
            }

            if ($this->forRemove->contains($subscription)) {
                continue;
            }

            $this->subscriptionStore->update($subscription);
        }

        foreach ($this->forRemove as $subscription) {
            if ($this->forAdd->contains($subscription)) {
                continue;
            }

            $this->subscriptionStore->remove($subscription);
        }

        $this->forAdd = new SplObjectStorage();
        $this->forUpdate = new SplObjectStorage();
        $this->forRemove = new SplObjectStorage();
    }
}
