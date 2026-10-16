<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription;

use Patchlevel\EventSourcing\Message\Stream;
use Patchlevel\EventSourcing\Store\Criteria\IndexCriterion;
use Patchlevel\EventSourcing\Store\UnsupportedCriterion;
use Patchlevel\EventSourcing\Subscription\Engine\IndexMessageLoader;
use Patchlevel\EventSourcing\Subscription\Engine\MessageLoader;
use Patchlevel\EventSourcing\Subscription\Subscription;

/** Behaves like a built-in loader on top of a custom event store that does not know the IndexCriterion. */
final class IndexCriterionNotSupportedMessageLoader implements IndexMessageLoader
{
    public function __construct(
        private readonly MessageLoader $loader,
    ) {
    }

    /** @param list<Subscription> $subscriptions */
    public function load(int|null $startIndex, array $subscriptions): Stream
    {
        return $this->loader->load($startIndex, $subscriptions);
    }

    /** @param non-empty-list<int> $indices */
    public function loadIndices(array $indices): Stream
    {
        throw new UnsupportedCriterion(IndexCriterion::class);
    }

    public function lastIndex(): int
    {
        return $this->loader->lastIndex();
    }
}
