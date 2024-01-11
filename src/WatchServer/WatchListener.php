<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\WatchServer;

use Patchlevel\EventSourcing\Attribute\Subscribe;
use Patchlevel\EventSourcing\EventBus\Message;

final class WatchListener
{
    public function __construct(
        private readonly WatchServerClient $client,
    ) {
    }

    #[Subscribe('*')]
    public function __invoke(Message $message): void
    {
        try {
            $this->client->send($message);
        } catch (SendingFailed) {
            // to nothing
        }
    }
}
