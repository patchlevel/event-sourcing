<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use function end;
use function explode;
use function method_exists;

trait AggregateRootBehaviour
{
    /** @var list<object> */
    private array $uncommittedEvents = [];

    private int $playhead = 0;

    final protected function __construct()
    {
    }

    protected function apply(object $event): void
    {
        $method = $this->findApplyMethod($event);

        if (!method_exists($this, $method)) {
            return;
        }

        $this->$method($event);
    }

    protected function recordThat(object $event): void
    {
        $this->playhead++;

        $this->apply($event);

        $this->uncommittedEvents[] = $event;
    }

    /**
     * @param list<object> $events
     */
    public function catchUp(array $events): void
    {
        foreach ($events as $event) {
            $this->playhead++;
            $this->apply($event);
        }
    }

    /**
     * @return list<object>
     */
    public function releaseEvents(): array
    {
        $events = $this->uncommittedEvents;
        $this->uncommittedEvents = [];

        return $events;
    }

    /**
     * @param list<object>   $events
     * @param 0|positive-int $startPlayhead
     */
    public static function createFromEvents(array $events, int $startPlayhead = 0): static
    {
        $self = new static();
        $self->playhead = $startPlayhead;
        $self->catchUp($events);

        return $self;
    }

    public function playhead(): int
    {
        return $this->playhead;
    }

    private function findApplyMethod(object $event): string
    {
        $classParts = explode('\\', $event::class);

        return 'apply' . end($classParts);
    }
}
