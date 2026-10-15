<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription;

use Closure;
use DateTimeImmutable;
use Patchlevel\EventSourcing\Clock\FrozenClock;
use Patchlevel\EventSourcing\Subscription\Store\InMemorySubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Store\PartitionInboxStore;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Subscription;
use PHPUnit\Framework\Assert;
use Psr\Clock\ClockInterface;

final class DummySubscriptionStore implements PartitionInboxStore
{
    private readonly ClockInterface $clock;

    private readonly InMemorySubscriptionStore $parentStore;

    /** @var list<Subscription> */
    public array $addedSubscriptions = [];

    /** @var list<Subscription> */
    public array $updatedSubscriptions = [];

    /** @var list<Subscription> */
    public array $removedSubscriptions = [];

    /** @var list<string> */
    public array $claimedIds = [];

    /** @param list<Subscription> $subscriptions */
    public function __construct(
        array $subscriptions = [],
    ) {
        $this->clock = new FrozenClock(new DateTimeImmutable('2021-01-01T00:00:00.000000+00:00'));
        $this->parentStore = new InMemorySubscriptionStore($subscriptions, $this->clock);
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

    /**
     * @param non-empty-list<string> $ids
     *
     * @return list<Subscription>
     */
    public function claim(array $ids, SubscriptionCriteria $criteria): array
    {
        foreach ($ids as $id) {
            $this->claimedIds[] = $id;
        }

        return $this->parentStore->claim($ids, $criteria);
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
        return $this->parentStore->inLock($closure);
    }

    public function add(Subscription ...$subscriptions): void
    {
        $this->parentStore->add(...$subscriptions);

        foreach ($subscriptions as $subscription) {
            $this->addedSubscriptions[] = clone $subscription;
        }
    }

    public function update(Subscription ...$subscriptions): void
    {
        $this->parentStore->update(...$subscriptions);

        foreach ($subscriptions as $subscription) {
            $this->updatedSubscriptions[] = clone $subscription;
        }
    }

    public function remove(Subscription ...$subscriptions): void
    {
        $this->parentStore->remove(...$subscriptions);

        foreach ($subscriptions as $subscription) {
            $this->removedSubscriptions[] = clone $subscription;
        }
    }

    /** @param array<string, non-empty-list<int>> $indices */
    public function addToInbox(array $indices): void
    {
        $this->parentStore->addToInbox($indices);
    }

    /**
     * @param array<string, int> $afterIndex
     * @param positive-int       $limit
     *
     * @return array<string, non-empty-list<int>>
     */
    public function inbox(array $afterIndex, int $limit): array
    {
        return $this->parentStore->inbox($afterIndex, $limit);
    }

    /**
     * @param positive-int $idleSeconds
     * @param positive-int $limit
     */
    public function removeIdlePartitions(string $subscriberId, int $idleSeconds, int $limit): int
    {
        return $this->parentStore->removeIdlePartitions($subscriberId, $idleSeconds, $limit);
    }

    public function reset(): void
    {
        $this->addedSubscriptions = [];
        $this->updatedSubscriptions = [];
        $this->removedSubscriptions = [];
    }

    public function assertNoChanges(): void
    {
        Assert::assertEmpty($this->addedSubscriptions);
        Assert::assertEmpty($this->updatedSubscriptions);
        Assert::assertEmpty($this->removedSubscriptions);
    }

    public function assertNoAdded(): void
    {
        Assert::assertEmpty($this->addedSubscriptions);
    }

    public function assertAdded(Subscription ...$subscriptions): void
    {
        foreach ($subscriptions as $subscription) {
            if ($subscription->lastSavedAt() !== null) {
                continue;
            }

            $subscription->updateLastSavedAt($this->clock->now());
        }

        Assert::assertEquals(
            $subscriptions,
            $this->addedSubscriptions,
        );
    }

    public function assertNoUpdated(): void
    {
        Assert::assertEmpty($this->updatedSubscriptions);
    }

    public function assertUpdated(Subscription ...$subscriptions): void
    {
        foreach ($subscriptions as $subscription) {
            if ($subscription->lastSavedAt() !== null) {
                continue;
            }

            $subscription->updateLastSavedAt($this->clock->now());
        }

        Assert::assertEquals(
            $subscriptions,
            $this->updatedSubscriptions,
        );
    }

    public function assertNoRemoved(): void
    {
        Assert::assertEmpty($this->removedSubscriptions);
    }

    public function assertRemoved(Subscription ...$subscriptions): void
    {
        Assert::assertEquals(
            $subscriptions,
            $this->removedSubscriptions,
        );
    }
}
