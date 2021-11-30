<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use function end;
use function explode;
use function get_class;
use function method_exists;

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

    protected function apply(AggregateChanged $event): void
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
