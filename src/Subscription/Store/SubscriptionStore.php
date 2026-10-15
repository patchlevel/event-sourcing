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

    /**
     * Like find(), but a store may load the error context and the cleanup tasks only when they are accessed.
     * The engine uses it for snapshots it only needs to decide what to claim.
     *
     * @return list<Subscription>
     */
    public function findLazy(SubscriptionCriteria $criteria): array;

    /** Claims one subscription via a row lock (SKIP LOCKED); null if held by another worker or no match. */
    public function claim(string $id, SubscriptionCriteria $criteria): Subscription|null;

    /**
     * Claims several subscriptions at once via row locks (SKIP LOCKED), leaving out the ones that are held by another
     * worker or no longer match.
     *
     * @param non-empty-list<string> $ids
     *
     * @return list<Subscription>
     */
    public function claimAll(array $ids, SubscriptionCriteria $criteria): array;

    /** @throws SubscriptionAlreadyExists */
    public function add(Subscription $subscription): void;

    /**
     * Adds several subscriptions at once.
     *
     * @param non-empty-list<Subscription> $subscriptions
     *
     * @throws SubscriptionAlreadyExists
     */
    public function addAll(array $subscriptions): void;

    /** @throws SubscriptionNotFound */
    public function update(Subscription $subscription): void;

    /**
     * Updates several subscriptions at once.
     *
     * @param non-empty-list<Subscription> $subscriptions
     *
     * @throws SubscriptionNotFound
     */
    public function updateAll(array $subscriptions): void;

    /**
     * Removes the subscription together with its inbox.
     *
     * @throws SubscriptionNotFound
     */
    public function remove(Subscription $subscription): void;

    /**
     * Adds message indices to the inboxes of partitions. The main subscription of a partitioned subscriber routes every
     * message exactly once, the partition then only processes the messages in its inbox.
     *
     * @param array<string, non-empty-list<int>> $indices subscription id => message indices
     */
    public function addToInbox(array $indices): void;

    /**
     * Returns at most $limit indices per subscription after the given index, in ascending order.
     * Subscriptions with an empty inbox are left out.
     *
     * @param array<string, int> $afterIndex subscription id => index after which the inbox is read
     * @param positive-int       $limit
     *
     * @return array<string, non-empty-list<int>>
     */
    public function inbox(array $afterIndex, int $limit): array;

    /**
     * Removes all indices up to and including the given index from the inboxes.
     *
     * @param array<string, int> $toIndex subscription id => index up to which the inbox is cleared
     */
    public function removeFromInbox(array $toIndex): void;

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
