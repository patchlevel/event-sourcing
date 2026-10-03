<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Event;

use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionEngineCriteria;

final class OnSubscriptions
{
    public function __construct(
        public readonly SubscriptionEngineCriteria $criteria,
    ) {
    }
}
