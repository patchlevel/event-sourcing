<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Event;

use Patchlevel\EventSourcing\Subscription\Engine\Error;
use Patchlevel\EventSourcing\Subscription\Subscription;

final class OnSubscriptionProcessed
{
    /** @var list<Error> */
    public array $errors = [];

    public function __construct(
        public readonly Subscription $subscription,
        public readonly int|null $lastIndex = null,
    ) {
    }
}
