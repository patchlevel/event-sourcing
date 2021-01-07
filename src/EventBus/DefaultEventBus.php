<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use function array_shift;

final class DefaultEventBus implements EventBus
{
    /** @var list<AggregateChanged> */
    private array $queue;
    /**  @var list<Listener> */
    private array $listeners;

    private bool $processing;

    public function __construct()
    {
        $this->queue = [];
        $this->listeners = [];
        $this->processing = false;
    }

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
