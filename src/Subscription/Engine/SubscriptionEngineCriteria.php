<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Patchlevel\EventSourcing\Subscription\Status;

/** @immutable */
final class SubscriptionEngineCriteria
{
    /**
     * @param list<string>|null $ids        matches the subscription id or the subscriber id
     * @param list<string>|null $groups
     * @param bool|null         $partitions true only matches partitions, false only the main subscriptions
     * @param list<Status>|null $status
     */
    public function __construct(
        public readonly array|null $ids = null,
        public readonly array|null $groups = null,
        public readonly bool|null $partitions = null,
        public readonly array|null $status = null,
    ) {
    }
}
