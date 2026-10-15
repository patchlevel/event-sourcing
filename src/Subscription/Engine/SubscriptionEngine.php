<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Patchlevel\EventSourcing\Subscription\Engine\Command\Command;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionStats;
use Patchlevel\EventSourcing\Subscription\Subscription;

interface SubscriptionEngine
{
    /** @throws AlreadyProcessing */
    public function execute(Command $command): Result;

    /** @return list<Subscription> */
    public function subscriptions(SubscriptionEngineCriteria|null $criteria = null): array;

    /** Counts the matching subscriptions per status, without loading them. */
    public function stats(SubscriptionEngineCriteria|null $criteria = null): SubscriptionStats;
}
