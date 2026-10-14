<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Store;

use Closure;
use Patchlevel\EventSourcing\Clock\SystemClock;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Psr\Clock\ClockInterface;

use function array_filter;
use function array_key_exists;
use function array_keys;
use function array_slice;
use function array_values;
use function in_array;
use function sort;

final class InMemorySubscriptionStore implements SubscriptionStore
{
    /** @var array<string, Subscription> */
    private array $subscriptions = [];

    /** @var array<string, array<int, true>> */
    private array $inbox = [];

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
                fn (Subscription $subscription): bool => $this->matches($subscription, $criteria),
            ),
        );
    }

    public function claim(string $id, SubscriptionCriteria $criteria): Subscription|null
    {
        if (!array_key_exists($id, $this->subscriptions)) {
            return null;
        }

        $subscription = $this->subscriptions[$id];

        if (!$this->matches($subscription, $criteria)) {
            return null;
        }

        return $subscription;
    }

    /**
     * @param non-empty-list<string> $ids
     *
     * @return list<Subscription>
     */
    public function claimAll(array $ids, SubscriptionCriteria $criteria): array
    {
        $claimed = [];

        foreach ($ids as $id) {
            $subscription = $this->claim($id, $criteria);

            if ($subscription === null) {
                continue;
            }

            $claimed[] = $subscription;
        }

        return $claimed;
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

    private function matches(Subscription $subscription, SubscriptionCriteria $criteria): bool
    {
        if ($criteria->ids !== null) {
            if (
                !in_array($subscription->id(), $criteria->ids, true)
                && !in_array($subscription->subscriberId(), $criteria->ids, true)
            ) {
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

        if ($criteria->partitions !== null && $subscription->isPartition() !== $criteria->partitions) {
            return false;
        }

        if ($criteria->pendingPartitionsOnly && $subscription->isPartition()) {
            return $this->inbox([$subscription->id() => $subscription->position() ?? -1], 1) !== [];
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

    /** @param non-empty-list<Subscription> $subscriptions */
    public function addAll(array $subscriptions): void
    {
        foreach ($subscriptions as $subscription) {
            $this->add($subscription);
        }
    }

    /** @param non-empty-list<Subscription> $subscriptions */
    public function updateAll(array $subscriptions): void
    {
        foreach ($subscriptions as $subscription) {
            $this->update($subscription);
        }
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
        unset($this->subscriptions[$subscription->id()], $this->inbox[$subscription->id()]);
    }

    /** @param array<string, non-empty-list<int>> $indices */
    public function addToInbox(array $indices): void
    {
        foreach ($indices as $subscriptionId => $messageIndices) {
            foreach ($messageIndices as $messageIndex) {
                $this->inbox[$subscriptionId][$messageIndex] = true;
            }
        }
    }

    /**
     * @param array<string, int> $afterIndex
     * @param positive-int       $limit
     *
     * @return array<string, non-empty-list<int>>
     */
    public function inbox(array $afterIndex, int $limit): array
    {
        $inbox = [];

        foreach ($afterIndex as $subscriptionId => $index) {
            $indices = array_values(array_filter(
                array_keys($this->inbox[$subscriptionId] ?? []),
                static fn (int $messageIndex): bool => $messageIndex > $index,
            ));

            if ($indices === []) {
                continue;
            }

            sort($indices);

            $inbox[$subscriptionId] = array_slice($indices, 0, $limit);
        }

        return $inbox;
    }

    /** @param array<string, int> $toIndex */
    public function removeFromInbox(array $toIndex): void
    {
        foreach ($toIndex as $subscriptionId => $index) {
            foreach (array_keys($this->inbox[$subscriptionId] ?? []) as $messageIndex) {
                if ($messageIndex > $index) {
                    continue;
                }

                unset($this->inbox[$subscriptionId][$messageIndex]);
            }
        }
    }

    public function clear(): void
    {
        $this->subscriptions = [];
        $this->inbox = [];
    }
}
