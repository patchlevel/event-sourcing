<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Event;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Throwable;

final class OnHandleMessageError
{
    public function __construct(
        public readonly Subscription $subscription,
        public readonly Throwable $throwable,
        public readonly Message $message,
        public readonly int $index,
        public bool $transitionToFailed = false,
    ) {
    }
}
