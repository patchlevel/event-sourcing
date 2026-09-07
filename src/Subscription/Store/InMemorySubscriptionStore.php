<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Store;

use Closure;
use Patchlevel\EventSourcing\Clock\SystemClock;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Psr\Clock\ClockInterface;

use function array_filter;
use function array_key_exists;
use function array_values;
use function in_array;

final class InMemorySubscriptionStore implements SubscriptionStore
{
    /** @var array<string, Subscription> */
    private array $subscriptions = [];

    /** @param list<Subscription> $subscriptions */
    public function __construct(
        array $subscriptions = [],
        private readonly ClockInterface $clock = new SystemClock(),
    ) {
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
                static fn (Subscription $subscription): bool => self::matches($subscription, $criteria),
            ),
        );
    }

    public function claim(string $id, SubscriptionCriteria $criteria): Subscription|null
    {
        if (!array_key_exists($id, $this->subscriptions)) {
            return null;
        }

        $subscription = $this->subscriptions[$id];

        if (!self::matches($subscription, $criteria)) {
            return null;
        }

        return $subscription;
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
        return $closure();
    }

    private static function matches(Subscription $subscription, SubscriptionCriteria $criteria): bool
    {
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
    }

    public function add(Subscription $subscription): void
    {
        if (array_key_exists($subscription->id(), $this->subscriptions)) {
            throw new SubscriptionAlreadyExists($subscription->id());
        }

        $subscription->updateLastSavedAt($this->clock->now());

        $this->subscriptions[$subscription->id()] = $subscription;
    }

    public function update(Subscription $subscription): void
    {
        if (!array_key_exists($subscription->id(), $this->subscriptions)) {
            throw new SubscriptionNotFound($subscription->id());
        }

        $subscription->updateLastSavedAt($this->clock->now());

        $this->subscriptions[$subscription->id()] = $subscription;
    }

    public function remove(Subscription $subscription): void
    {
        unset($this->subscriptions[$subscription->id()]);
    }

    public function clear(): void
    {
        $this->subscriptions = [];
    }
}
