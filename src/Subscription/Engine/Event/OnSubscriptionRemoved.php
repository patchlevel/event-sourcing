<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Event;

use Patchlevel\EventSourcing\Subscription\Subscription;

final class OnSubscriptionRemoved
{
    public function __construct(
        public readonly Subscription $subscription,
    ) {
    }
}
