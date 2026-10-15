<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Console\Command;

use Patchlevel\EventSourcing\Subscription\Engine\Command\Command;
use Patchlevel\EventSourcing\Subscription\Engine\Result;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngine;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;
use Patchlevel\EventSourcing\Subscription\Subscription;

use function array_filter;
use function array_values;
use function in_array;

/** Filters like the subscription store and records every criteria it gets. */
final class FakeSubscriptionEngine implements SubscriptionEngine
{
    /** @var list<SubscriptionEngineCriteria> */
    public array $criteria = [];

    /** @param list<Subscription> $subscriptions */
    public function __construct(
        private readonly array $subscriptions,
    ) {
    }

    public function execute(Command $command): Result
    {
        return new Result();
    }

    /** @return list<Subscription> */
    public function subscriptions(SubscriptionEngineCriteria|null $criteria = null): array
    {
        $criteria ??= new SubscriptionEngineCriteria();
        $this->criteria[] = $criteria;

        return array_values(array_filter(
            $this->subscriptions,
            static fn (Subscription $subscription): bool => ($criteria->ids === null
                    || in_array($subscription->id(), $criteria->ids, true)
                    || in_array($subscription->subscriberId(), $criteria->ids, true))
                && ($criteria->groups === null || in_array($subscription->group(), $criteria->groups, true))
                && ($criteria->status === null || in_array($subscription->status(), $criteria->status, true))
                && ($criteria->partitions === null || $subscription->isPartition() === $criteria->partitions),
        ));
    }
}
