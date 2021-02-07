<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\WatchServer;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\EventBus\Listener;

class WatchListener implements Listener
{
    private WatchServerClient $client;

    public function __construct(WatchServerClient $client)
    {
        $this->client = $client;
    }

    public function __invoke(AggregateChanged $event): void
    {
        try {
            $this->client->send($event);
        } catch (SendingFailed $exception) {
            // to nothing
        }
    }
}
