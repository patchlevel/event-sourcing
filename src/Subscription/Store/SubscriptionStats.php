<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Store;

use Patchlevel\EventSourcing\Subscription\Status;

use function array_sum;

/** Figures about the subscriptions that match a criteria, without loading them. */
final class SubscriptionStats
{
    /** @param array<value-of<Status>, int> $status number of subscriptions per status, statuses without any are left out */
    public function __construct(
        public readonly array $status = [],
        /** the inboxes of the matching partitions, null if the store has no partitions support */
        public readonly InboxStats|null $inbox = null,
    ) {
    }

    public function total(): int
    {
        return array_sum($this->status);
    }

    public function count(Status $status): int
    {
        return $this->status[$status->value] ?? 0;
    }
}
