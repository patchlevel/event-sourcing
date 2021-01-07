<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

interface EventBus
{
    public function dispatch(AggregateChanged $event): void;

    public function addListener(Listener $listener): void;
}
