<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use function end;
use function explode;
use function get_class;
use function method_exists;

abstract class AggregateRoot
{
    /**
     * @var AggregateChanged[]
     */
    private array $uncommittedEvents = [];
    private int $playhead = -1;

    abstract public function aggregateRootId(): string;

    public function apply(AggregateChanged $event): void
    {
        $this->playhead++;
        $event = $event->recordNow($this->playhead);
        $this->uncommittedEvents[] = $event;

        $this->handle($event);
    }

    /**
     * @return AggregateChanged[]
     */
    public function releaseEvents(): array
    {
        $events = $this->uncommittedEvents;
        $this->uncommittedEvents = [];

        return $events;
    }

    /**
     * @param AggregateChanged[] $stream
     */
    public function initializeState(array $stream): void
    {
        foreach ($stream as $message) {
            $this->playhead++;
            $this->handle($message);
        }
    }

    protected function handle(AggregateChanged $event): void
    {
        $method = $this->findApplyMethod($event);

        if (!method_exists($this, $method)) {
            return;
        }

        $this->$method($event);
    }

    private function findApplyMethod(AggregateChanged $event): string
    {
        $classParts = explode('\\', get_class($event));

        return 'apply' . end($classParts);
    }

    public function playhead(): int
    {
        return $this->playhead;
    }
}
