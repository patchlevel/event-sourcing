<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\WatchServer;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

interface WatchServerClient
{
    public function send(AggregateChanged $event): void;
}
