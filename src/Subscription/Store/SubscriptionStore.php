<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Store;

use Closure;
use Patchlevel\EventSourcing\Subscription\Subscription;

interface SubscriptionStore
{
    /** @throws SubscriptionNotFound */
    public function get(string $subscriptionId): Subscription;

    /**
     * A store may load the error context and the cleanup tasks of the found subscriptions only when they are accessed.
     *
     * @return list<Subscription>
     */
    public function find(SubscriptionCriteria|null $criteria = null): array;

    /** Counts the matching subscriptions per status, without loading them. */
    public function stats(SubscriptionCriteria $criteria): SubscriptionStats;

    /**
     * Claims the subscriptions via row locks (SKIP LOCKED), leaving out the ones that are held by another worker or no
     * longer match the criteria.
     *
     * @param non-empty-list<string> $ids
     *
     * @return list<Subscription>
     */
    public function claim(array $ids, SubscriptionCriteria $criteria): array;

    /** @throws SubscriptionAlreadyExists */
    public function add(Subscription ...$subscriptions): void;

    /** @throws SubscriptionNotFound */
    public function update(Subscription ...$subscriptions): void;

    public function remove(Subscription ...$subscriptions): void;

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
