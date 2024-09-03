<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use Patchlevel\Hydrator\Attribute\Ignore;
use Patchlevel\Hydrator\Attribute\NormalizedName;

trait AggregateRootBehaviour
{
    /** @var list<object> */
    #[Ignore]
    private array $uncommittedEvents = [];

    #[NormalizedName('_playhead')]
    private int $playhead = 0;

    final protected function __construct()
    {
    }

    abstract protected function apply(object $event): void;

    /** @param iterable<object> $events */
    public function catchUp(iterable $events): void
    {
        foreach ($events as $event) {
            $this->record($event);
        }
    }

    /** @return list<object> */
    public function releaseEvents(): array
    {
        $events = $this->uncommittedEvents;
        $this->uncommittedEvents = [];

        return $events;
    }

    /**
     * @param iterable<object> $events
     * @param 0|positive-int   $startPlayhead
     */
    public static function createFromEvents(iterable $events, int $startPlayhead = 0): static
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

    protected function recordThat(object $event): void
    {
        $this->record($event);
        $this->uncommittedEvents[] = $event;
    }

    private function record(object $event): void
    {
        $this->playhead++;
        $this->apply($event);
    }
}
