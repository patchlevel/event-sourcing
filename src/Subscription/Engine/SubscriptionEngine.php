<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Patchlevel\EventSourcing\Subscription\Engine\Command\Command;
use Patchlevel\EventSourcing\Subscription\Subscription;

interface SubscriptionEngine
{
    /** @throws AlreadyProcessing */
    public function run(Command $command): Result;

    /** @return list<Subscription> */
    public function subscriptions(SubscriptionEngineCriteria|null $criteria = null): array;
}
