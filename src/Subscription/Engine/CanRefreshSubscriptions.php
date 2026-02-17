<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

interface CanRefreshSubscriptions
{
    public function refresh(SubscriptionEngineCriteria|null $criteria = null): Result;
}
