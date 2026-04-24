<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Patchlevel\EventSourcing\Message\Stream;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\FromIndexCriterion;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Subscription\Subscription;

final class StoreMessageLoader implements MessageLoader
{
    public function __construct(
        private readonly Store $store,
    ) {
    }

    /** @param list<Subscription> $subscriptions */
    public function load(int|null $startIndex, array $subscriptions): Stream
    {
        $criteria = new Criteria();

        if ($startIndex !== null) {
            $criteria = $criteria->add(new FromIndexCriterion($startIndex));
        }

        return $this->store->load($criteria);
    }

    public function lastIndex(): int
    {
        $stream = $this->store->load(null, 1, null, true);

        return $stream->index() ?: 0;
    }
}
