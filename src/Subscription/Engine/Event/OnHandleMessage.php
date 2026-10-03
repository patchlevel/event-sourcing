<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Event;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Subscription\Subscription;

final class OnHandleMessage
{
    public function __construct(
        public readonly Subscription $subscription,
        public readonly Message $message,
    ) {
    }
}
