<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Store;

use Closure;
use Patchlevel\EventSourcing\Subscription\Subscription;

interface SubscriptionStore
{
    /** @throws SubscriptionNotFound */
    public function get(string $subscriptionId): Subscription;

    /** @return list<Subscription> */
    public function find(SubscriptionCriteria|null $criteria = null): array;

    /** Claims one subscription via a row lock (SKIP LOCKED); null if held by another worker or no match. */
    public function claim(string $id, SubscriptionCriteria $criteria): Subscription|null;

    /** @throws SubscriptionAlreadyExists */
    public function add(Subscription $subscription): void;

    /** @throws SubscriptionNotFound */
    public function update(Subscription $subscription): void;

    /** @throws SubscriptionNotFound */
    public function remove(Subscription $subscription): void;

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
