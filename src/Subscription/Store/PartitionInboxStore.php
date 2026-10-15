<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Store;

/**
 * A subscription store that supports partitions. The main subscription of a partitioned subscriber routes every message
 * exactly once into the inbox of a partition, the partition then only processes the messages in its inbox.
 *
 * The inbox of a partition only holds messages after its position: updating a partition removes the messages up to its
 * position, removing a partition removes its inbox.
 */
interface PartitionInboxStore extends SubscriptionStore
{
    /** @param array<string, non-empty-list<int>> $indices subscription id => message indices */
    public function addToInbox(array $indices): void;

    /**
     * Returns at most $limit indices per partition after its position, in ascending order.
     * Partitions with an empty inbox are left out.
     *
     * @param array<string, int> $afterIndex subscription id => position of the partition
     * @param positive-int       $limit
     *
     * @return array<string, non-empty-list<int>>
     */
    public function inbox(array $afterIndex, int $limit): array;

    /**
     * Removes active partitions of the subscriber with an empty inbox that were not saved for the given seconds.
     * Partitions that are held by another worker are left out. Only call it while holding the lock of the main
     * subscription, otherwise it could remove a partition the main subscription routes into at the same time.
     *
     * @param positive-int $idleSeconds
     * @param positive-int $limit
     *
     * @return int how many partitions were removed
     */
    public function removeIdlePartitions(string $subscriberId, int $idleSeconds, int $limit): int;
}
