<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\WatchServer;

use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\EventBus\Message;

final class WatchEventBus implements EventBus
{
    public function __construct(
        private readonly WatchServerClient $watchServerClient,
    ) {
    }

    public function dispatch(Message ...$messages): void
    {
        try {
            foreach ($messages as $message) {
                $this->watchServerClient->send($message);
            }
        } catch (SendingFailed) {
            // to nothing
        }
    }
}
