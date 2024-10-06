<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\FromIndexCriterion;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\Stream;
use Patchlevel\EventSourcing\Subscription\Subscription;

final class DefaultMessageLoader implements MessageLoader
{
    public function __construct(
        private readonly Store $store,
    ) {
    }

    /** @param list<Subscription> $subscriptions */
    public function load(int $startIndex, array $subscriptions): Stream
    {
        return $this->store->load(new Criteria(new FromIndexCriterion($startIndex)));
    }

    public function lastIndex(): int
    {
        $stream = $this->store->load(null, 1, null, true);

        return $stream->index() ?: 0;
    }
}
