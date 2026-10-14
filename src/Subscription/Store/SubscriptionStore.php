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

    /**
     * Removes the subscription together with its inbox.
     *
     * @throws SubscriptionNotFound
     */
    public function remove(Subscription $subscription): void;

    /**
     * Adds message indices to the inbox of a partition. The main subscription of a partitioned subscriber routes every
     * message exactly once, the partition then only processes the messages in its inbox.
     *
     * @param list<int> $indices
     */
    public function addToInbox(string $subscriptionId, array $indices): void;

    /**
     * Returns the indices in the inbox after the given index in ascending order.
     *
     * @param positive-int $limit
     *
     * @return list<int>
     */
    public function inbox(string $subscriptionId, int $afterIndex, int $limit): array;

    /** Removes all indices up to and including the given index from the inbox. */
    public function removeFromInbox(string $subscriptionId, int $toIndex): void;

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
