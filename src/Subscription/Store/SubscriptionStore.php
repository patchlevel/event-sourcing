<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Store;

use Patchlevel\EventSourcing\Subscription\Subscription;

interface SubscriptionStore
{
    /** @throws SubscriptionNotFound */
    public function get(string $subscriptionId): Subscription;

    /** @return list<Subscription> */
    public function find(SubscriptionCriteria|null $criteria = null): array;

    /** @throws SubscriptionAlreadyExists */
    public function add(Subscription $subscription): void;

    /** @throws SubscriptionNotFound */
    public function update(Subscription $subscription): void;

    /** @throws SubscriptionNotFound */
    public function remove(Subscription $subscription): void;
}
