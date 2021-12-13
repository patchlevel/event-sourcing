<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\WatchServer;

use Closure;
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

interface WatchServer
{
    public function start(): void;

    /**
     * @param Closure(AggregateChanged, int):void $callback
     */
    public function listen(Closure $callback): void;

    public function host(): string;
}
