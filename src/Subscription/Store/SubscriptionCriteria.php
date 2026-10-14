<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Store;

use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\SubscriptionType;

/** @immutable */
final class SubscriptionCriteria
{
    /**
     * Subscriptions without a shard always match the shards filter.
     *
     * @param list<string>|null           $ids
     * @param list<string>|null           $groups
     * @param list<Status>|null           $status
     * @param list<string>|null           $subscriberIds
     * @param list<SubscriptionType>|null $types
     * @param list<int>|null              $shards
     */
    public function __construct(
        public readonly array|null $ids = null,
        public readonly array|null $groups = null,
        public readonly array|null $status = null,
        public readonly array|null $subscriberIds = null,
        public readonly array|null $types = null,
        public readonly array|null $shards = null,
    ) {
    }
}
