<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\WatchServer;

use Patchlevel\EventSourcing\EventBus\Listener;
use Patchlevel\EventSourcing\EventBus\Message;

final class WatchListener implements Listener
{
    public function __construct(
        private WatchServerClient $client,
    ) {
    }

    public function __invoke(Message $message): void
    {
        try {
            $this->client->send($message);
        } catch (SendingFailed) {
            // to nothing
        }
    }
}
