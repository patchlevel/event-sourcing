<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

interface EventBus
{
    /**
     * @param AggregateChanged<array<string, mixed>> $event
     */
    public function dispatch(AggregateChanged $event): void;
}
