<?php

namespace Patchlevel\EventSourcing\Tool\Watch;

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
        $this->client->send($event);
    }
}
