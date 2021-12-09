<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

use function array_shift;

final class DefaultEventBus implements EventBus
{
    /** @var list<AggregateChanged<array<string, mixed>>> */
    private array $queue;
    /**  @var list<Listener> */
    private array $listeners;

    private bool $processing;

    /**
     * @param list<Listener> $listeners
     */
    public function __construct(array $listeners = [])
    {
        $this->queue = [];
        $this->listeners = $listeners;
        $this->processing = false;
    }

    /**
     * @param AggregateChanged<array<string, mixed>> $event
     */
    public function dispatch(AggregateChanged $event): void
    {
        $this->queue[] = $event;

        if ($this->processing) {
            return;
        }

        $this->processing = true;

        while ($event = array_shift($this->queue)) {
            foreach ($this->listeners as $listener) {
                $listener($event);
            }
        }

        $this->processing = false;
    }

    public function addListener(Listener $listener): void
    {
        $this->listeners[] = $listener;
    }
}
