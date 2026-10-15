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
        /**
         * A listener can stop the processing of the subscription for the rest of the run: no subscribe method is
         * called, the position stays in front of this message and the following messages are not processed either.
         * They are processed in a later run.
         */
        public bool $stop = false,
    ) {
    }
}
