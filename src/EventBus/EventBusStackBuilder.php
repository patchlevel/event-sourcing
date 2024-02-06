<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

final class EventBusStackBuilder
{
    /** @var list<EventBus> */
    private array $stack = [];

    public function addFist(EventBus $eventBus): self
    {
        $this->stack = [$eventBus, ...$this->stack];

        return $this;
    }

    public function addLast(EventBus $eventBus): self
    {
        $this->stack[] = $eventBus;

        return $this;
    }

    public function build(): EventBus
    {
        return new ChainEventBus($this->stack);
    }
}
