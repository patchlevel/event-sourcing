<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\WatchServer;

use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\EventBus\Message;

final class WatchEventBusWrapper implements EventBus
{
    public function __construct(
        private EventBus $eventBus,
        private WatchServerClient $client,
    ) {
    }

    public function dispatch(Message ...$messages): void
    {
        try {
            foreach ($messages as $message) {
                $this->client->send($message);
            }
        } catch (SendingFailed) {
            // to nothing
        }

        $this->eventBus->dispatch(...$messages);
    }
}
