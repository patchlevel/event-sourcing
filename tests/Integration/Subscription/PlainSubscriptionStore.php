<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\Subscription;

use Closure;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionStats;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Subscription;

/** A subscription store without an inbox, like a custom one that only implements the base interface. */
final class PlainSubscriptionStore implements SubscriptionStore
{
    public function __construct(
        private readonly SubscriptionStore $store,
    ) {
    }

    public function get(string $subscriptionId): Subscription
    {
        return $this->store->get($subscriptionId);
    }

    public function stats(SubscriptionCriteria $criteria): SubscriptionStats
    {
        return $this->store->stats($criteria);
    }

    /** @return list<Subscription> */
    public function find(SubscriptionCriteria|null $criteria = null): array
    {
        return $this->store->find($criteria);
    }

    /**
     * @param non-empty-list<string> $ids
     *
     * @return list<Subscription>
     */
    public function claim(array $ids, SubscriptionCriteria $criteria): array
    {
        return $this->store->claim($ids, $criteria);
    }

    public function add(Subscription ...$subscriptions): void
    {
        $this->store->add(...$subscriptions);
    }

    public function update(Subscription ...$subscriptions): void
    {
        $this->store->update(...$subscriptions);
    }

    public function remove(Subscription ...$subscriptions): void
    {
        $this->store->remove(...$subscriptions);
    }

    /**
     * @param Closure():T $closure
     *
     * @return T
     *
     * @template T
     */
    public function inLock(Closure $closure): mixed
    {
        return $this->store->inLock($closure);
    }
}
