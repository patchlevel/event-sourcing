<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use function array_shift;

final class DefaultEventBus implements EventBus
{
    /** @var list<AggregateChanged> */
    private array $queue;
    /**  @var array<class-string<AggregateChanged>, list<Listener>> */
    private array $listeners;
    /**  @var list<Listener> */
    private array $allListener;

    private bool $processing;

    public function __construct()
    {
        $this->queue = [];
        $this->allListener = [];
        $this->listeners = [];
        $this->processing = false;
    }

    public function dispatch(AggregateChanged $event): void
    {
        $this->queue[] = $event;

        if ($this->processing) {
            return;
        }

        $listeners = array_merge(
            $this->allListener,
            $this->listeners[get_class($event)] ?? []
        );

        $this->processing = true;

        while ($event = array_shift($this->queue)) {
            foreach ($listeners as $listener) {
                $listener($event);
            }
        }

        $this->processing = false;
    }

    public function addListener(string $eventName, Listener $listener): void
    {
        $this->listeners[$eventName][] = $listener;
    }

    public function addListenerForAll(Listener $listener): void
    {
        $this->allListener[] = $listener;
    }
}
