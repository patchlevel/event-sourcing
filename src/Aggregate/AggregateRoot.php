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

    /**
     * @param AggregateChanged<array<string, mixed>> $event
     */
    final protected function record(AggregateChanged $event): void
    {
        $this->playhead++;

        $event = $event->recordNow($this->playhead);
        $this->uncommittedEvents[] = $event;

        $this->apply($event);
    }

    /**
     * @return array<AggregateChanged>
     */
    final public function releaseEvents(): array
    {
        $events = $this->uncommittedEvents;
        $this->uncommittedEvents = [];

        return $events;
    }

    /**
     * @param array<AggregateChanged> $stream
     */
    final public static function createFromEventStream(array $stream): static
    {
        $self = new static();

        foreach ($stream as $message) {
            $self->playhead++;

            if ($self->playhead !== $message->playhead()) {
                throw new PlayheadSequenceMismatch();
            }

            $self->apply($message);
        }

        return $self;
    }

    final public function playhead(): int
    {
        return $this->playhead;
    }
}
