<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

final class ChainEventBus implements EventBus
{
    /** @param iterable<EventBus> $eventBuses */
    public function __construct(
        private readonly iterable $eventBuses,
    ) {
    }

    public function dispatch(Message ...$messages): void
    {
        foreach ($this->eventBuses as $eventBus) {
            $eventBus->dispatch(...$messages);
        }
    }
}
