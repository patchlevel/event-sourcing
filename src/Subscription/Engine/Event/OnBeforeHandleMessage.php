<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Event;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Subscription\Subscription;

/**
 * Dispatched before a message is passed to a subscriber, before OnHandleMessage. A listener can stop the processing of
 * the subscription here, then OnHandleMessage is not dispatched at all, so every OnHandleMessage is still followed by
 * exactly one OnHandleMessageSuccess or OnHandleMessageError.
 */
final class OnBeforeHandleMessage
{
    public function __construct(
        public readonly Subscription $subscription,
        public readonly Message $message,
        /**
         * Stops the processing of the subscription for the rest of the run: no subscribe method is called, the
         * position stays in front of this message and the following messages are not processed either.
         * They are processed in a later run.
         */
        public bool $stop = false,
    ) {
    }
}
