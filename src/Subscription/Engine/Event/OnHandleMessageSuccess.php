<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Event;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Subscription\Subscription;

final class OnHandleMessageSuccess
{
    public function __construct(
        public readonly Subscription $subscription,
        public readonly Message $message,
        public readonly int $index,
        public bool $shouldChangePosition = true,
    ) {
    }
}
