<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Store;

use Patchlevel\EventSourcing\Subscription\Subscription;

use function array_filter;
use function array_key_exists;
use function array_values;
use function in_array;

final class InMemorySubscriptionStore implements SubscriptionStore
{
    /** @var array<string, Subscription> */
    private array $subscriptions = [];

    /** @param list<Subscription> $subscriptions */
    public function __construct(array $subscriptions = [])
    {
        foreach ($subscriptions as $subscription) {
            $this->subscriptions[$subscription->id()] = $subscription;
        }
    }

    public function get(string $subscriptionId): Subscription
    {
        if (array_key_exists($subscriptionId, $this->subscriptions)) {
            return $this->subscriptions[$subscriptionId];
        }

        throw new SubscriptionNotFound($subscriptionId);
    }

    /** @return list<Subscription> */
    public function find(SubscriptionCriteria|null $criteria = null): array
    {
        $subscriptions = array_values($this->subscriptions);

        if ($criteria === null) {
            return $subscriptions;
        }

        return array_values(
            array_filter(
                $subscriptions,
                static function (Subscription $subscription) use ($criteria): bool {
                    if ($criteria->ids !== null) {
                        if (!in_array($subscription->id(), $criteria->ids, true)) {
                            return false;
                        }
                    }

                    if ($criteria->groups !== null) {
                        if (!in_array($subscription->group(), $criteria->groups, true)) {
                            return false;
                        }
                    }

                    if ($criteria->status !== null) {
                        if (!in_array($subscription->status(), $criteria->status, true)) {
                            return false;
                        }
                    }

                    return true;
                },
            ),
        );
    }

    public function add(Subscription $subscription): void
    {
        if (array_key_exists($subscription->id(), $this->subscriptions)) {
            throw new SubscriptionAlreadyExists($subscription->id());
        }

        $this->subscriptions[$subscription->id()] = $subscription;
    }

    public function update(Subscription $subscription): void
    {
        if (!array_key_exists($subscription->id(), $this->subscriptions)) {
            throw new SubscriptionNotFound($subscription->id());
        }

        $this->subscriptions[$subscription->id()] = $subscription;
    }

    public function remove(Subscription $subscription): void
    {
        unset($this->subscriptions[$subscription->id()]);
    }
}
