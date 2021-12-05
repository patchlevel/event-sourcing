<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

interface Listener
{
    /**
     * @param AggregateChanged<array<string, mixed>> $event
     */
    public function __invoke(AggregateChanged $event): void;
}
