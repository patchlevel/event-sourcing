<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Store;

use Patchlevel\EventSourcing\Subscription\Status;

/** @immutable */
final class SubscriptionCriteria
{
    /**
     * @param list<string>|null $ids                   matches the subscription id or the subscriber id,
     *                                                 so the id of a partitioned subscriber includes all its partitions
     * @param list<string>|null $groups
     * @param list<Status>|null $status
     * @param bool|null         $partitions            true only matches partitions, false only the main subscriptions
     * @param bool              $pendingPartitionsOnly partitions only match if their inbox has messages after their position
     */
    public function __construct(
        public readonly array|null $ids = null,
        public readonly array|null $groups = null,
        public readonly array|null $status = null,
        public readonly bool|null $partitions = null,
        public readonly bool $pendingPartitionsOnly = false,
    ) {
    }
}
