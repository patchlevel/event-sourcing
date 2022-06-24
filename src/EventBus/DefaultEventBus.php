<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

use function array_shift;

final class DefaultEventBus implements EventBus
{
    /** @var array<Message<object>> */
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

    public function dispatch(Message ...$messages): void
    {
        $this->queue += $messages;

        if ($this->processing) {
            return;
        }

        $this->processing = true;

        while ($message = array_shift($this->queue)) {
            foreach ($this->listeners as $listener) {
                $listener($message);
            }
        }

        $this->processing = false;
    }

    public function addListener(Listener $listener): void
    {
        $this->listeners[] = $listener;
    }
}
