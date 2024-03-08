<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Store;

use Patchlevel\EventSourcing\Subscription\Status;

/** @psalm-immutable */
final class SubscriptionCriteria
{
    /**
     * @param list<string>|null $ids
     * @param list<string>|null $groups
     * @param list<Status>|null $status
     */
    public function __construct(
        public readonly array|null $ids = null,
        public readonly array|null $groups = null,
        public readonly array|null $status = null,
    ) {
    }
}
