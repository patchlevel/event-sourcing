<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Patchlevel\EventSourcing\Subscription\Subscription;

interface SubscriptionEngine
{
    public function setup(SubscriptionEngineCriteria|null $criteria = null, bool $skipBooting = false): Result;

    /**
     * @param positive-int|null $limit
     *
     * @throws SubscriberNotFound
     * @throws AlreadyProcessing
     */
    public function boot(
        SubscriptionEngineCriteria|null $criteria = null,
        int|null $limit = null,
    ): ProcessedResult;

    /**
     * @param positive-int|null $limit
     *
     * @throws SubscriberNotFound
     * @throws AlreadyProcessing
     */
    public function run(
        SubscriptionEngineCriteria|null $criteria = null,
        int|null $limit = null,
    ): ProcessedResult;

    public function teardown(SubscriptionEngineCriteria|null $criteria = null): Result;

    public function remove(SubscriptionEngineCriteria|null $criteria = null): Result;

    public function reactivate(SubscriptionEngineCriteria|null $criteria = null): Result;

    public function pause(SubscriptionEngineCriteria|null $criteria = null): Result;

    /** @return list<Subscription> */
    public function subscriptions(SubscriptionEngineCriteria|null $criteria = null): array;
}
