<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Subscription;

use Closure;
use DateTimeImmutable;
use Patchlevel\EventSourcing\Clock\FrozenClock;
use Patchlevel\EventSourcing\Subscription\Store\InMemorySubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionStore;
use Patchlevel\EventSourcing\Subscription\Subscription;
use PHPUnit\Framework\Assert;
use Psr\Clock\ClockInterface;

final class DummySubscriptionStore implements SubscriptionStore
{
    private readonly ClockInterface $clock;

    private readonly InMemorySubscriptionStore $parentStore;

    /** @var list<Subscription> */
    public array $addedSubscriptions = [];

    /** @var list<Subscription> */
    public array $updatedSubscriptions = [];

    /** @var list<Subscription> */
    public array $removedSubscriptions = [];

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

    public function claim(string $id, SubscriptionCriteria $criteria): Subscription|null
    {
        return $this->parentStore->claim($id, $criteria);
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
