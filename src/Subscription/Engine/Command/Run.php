<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Command;

final class Run extends Command
{
    /**
     * @param list<string>|null $ids
     * @param list<string>|null $groups
     * @param positive-int|null $limit
     * @param list<int>|null    $shards only partitions of these shards, subscriptions without a shard are always included
     */
    public function __construct(
        array|null $ids = null,
        array|null $groups = null,
        public readonly int|null $limit = null,
        public readonly array|null $shards = null,
    ) {
        parent::__construct($ids, $groups);
    }
}
