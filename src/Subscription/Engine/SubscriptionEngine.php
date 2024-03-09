<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Patchlevel\EventSourcing\Subscription\Subscription;

interface SubscriptionEngine
{
    public function setup(SubscriptionEngineCriteria|null $criteria = null, bool $skipBooting = false): void;

    /**
     * @param positive-int|null $limit
     *
     * @throws SubscriberNotFound
     */
    public function boot(
        SubscriptionEngineCriteria|null $criteria = null,
        int|null $limit = null,
    ): void;

    /**
     * @param positive-int|null $limit
     *
     * @throws SubscriberNotFound
     */
    public function run(
        SubscriptionEngineCriteria|null $criteria = null,
        int|null $limit = null,
    ): void;

    public function teardown(SubscriptionEngineCriteria|null $criteria = null): void;

    public function remove(SubscriptionEngineCriteria|null $criteria = null): void;

    public function reactivate(SubscriptionEngineCriteria|null $criteria = null): void;

    public function pause(SubscriptionEngineCriteria|null $criteria = null): void;

    /** @return list<Subscription> */
    public function subscriptions(SubscriptionEngineCriteria|null $criteria = null): array;
}
