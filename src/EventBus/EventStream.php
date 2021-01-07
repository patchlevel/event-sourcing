<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use function array_shift;

final class EventStream implements EventBus
{
    /** @var list<AggregateChanged> */
    private array $queue;
    /**  @var array<class-string<AggregateChanged>, list<Listener>> */
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
        $listeners = $this->listeners[get_class($event)] ?? [];

        if (count($listeners) === 0) {
            return;
        }

        $this->queue[] = $event;

        if ($this->processing) {
            return;
        }

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

    public function addSubscriber(Subscriber $subscriber): void
    {

    }
}
