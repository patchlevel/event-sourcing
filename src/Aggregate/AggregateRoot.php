<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

abstract class AggregateRoot
{
    /** @var array<AggregateChanged> */
    private array $uncommittedEvents = [];

    /** @internal */
    protected int $playhead = 0;

    final protected function __construct()
    {
    }

    abstract public function aggregateRootId(): string;

    abstract protected function apply(AggregateChanged $event): void;

    protected function record(AggregateChanged $event): void
    {
        $this->playhead++;

        $event = $event->recordNow($this->playhead);
        $this->uncommittedEvents[] = $event;

        $this->apply($event);
    }

    /**
     * @return array<AggregateChanged>
     */
    public function releaseEvents(): array
    {
        $events = $this->uncommittedEvents;
        $this->uncommittedEvents = [];

        return $events;
    }

    /**
     * @param array<AggregateChanged> $stream
     *
     * @return static
     */
    public static function createFromEventStream(array $stream): self
    {
        $self = new static();

        foreach ($stream as $message) {
            $self->playhead++;
            $self->apply($message);
        }

        return $self;
    }

    public function playhead(): int
    {
        return $this->playhead;
    }
}
