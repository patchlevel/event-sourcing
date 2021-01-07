<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

interface EventBus
{
    public function dispatch(AggregateChanged $event): void;

    /**
     * @param class-string<AggregateChanged> $eventName
     */
    public function addListener(string $eventName, Listener $listener): void;

    public function addListenerForAll(Listener $listener): void;
}
