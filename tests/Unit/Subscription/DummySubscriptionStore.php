<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription;

use Patchlevel\EventSourcing\Subscription\Store\InMemorySubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Subscription;

final class DummySubscriptionStore implements SubscriptionStore
{
    private InMemorySubscriptionStore $parentStore;

    /** @var list<Subscription> */
    public array $addedSubscriptions = [];

    /** @var list<Subscription> */
    public array $updatedSubscriptions = [];

    /** @var list<Subscription> */
    public array $removedSubscriptions = [];

    /** @param list<Subscription> $subscriptions */
    public function __construct(array $subscriptions = [])
    {
        $this->parentStore = new InMemorySubscriptionStore($subscriptions);
    }

    public function get(string $subscriptionId): Subscription
    {
        return $this->parentStore->get($subscriptionId);
    }

    /** @return list<Subscription> */
    public function find(SubscriptionCriteria|null $criteria = null): array
    {
        return $this->parentStore->find($criteria);
    }

    public function add(Subscription $subscription): void
    {
        $this->parentStore->add($subscription);
        $this->addedSubscriptions[] = clone $subscription;
    }

    public function update(Subscription $subscription): void
    {
        $this->parentStore->update($subscription);
        $this->updatedSubscriptions[] = clone $subscription;
    }

    public function remove(Subscription $subscription): void
    {
        $this->parentStore->remove($subscription);
        $this->removedSubscriptions[] = clone $subscription;
    }

    public function reset(): void
    {
        $this->addedSubscriptions = [];
        $this->updatedSubscriptions = [];
        $this->removedSubscriptions = [];
    }
}
