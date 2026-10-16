<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Command;

final class Boot extends Command
{
    /**
     * @param list<string>|null $ids
     * @param list<string>|null $groups
     * @param positive-int|null $limit
     * @param positive-int      $subscriptionsPerClaim how many subscriptions are claimed and processed together
     *                                                in one transaction, they read the stream once from the lowest position
     * @param positive-int      $partitionsPerClaim    how many partitions are claimed and processed together in one transaction
     */
    public function __construct(
        array|null $ids = null,
        array|null $groups = null,
        public readonly int|null $limit = null,
        public readonly int $subscriptionsPerClaim = 1,
        public readonly int $partitionsPerClaim = 100,
    ) {
        parent::__construct($ids, $groups);
    }
}
