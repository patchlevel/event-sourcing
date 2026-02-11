<?php

namespace Patchlevel\EventSourcing\Subscription\Engine;

interface SubscriptionRefreshable
{
    public function refreshSubscriptions(SubscriptionEngineCriteria|null $criteria = null): Result;
}