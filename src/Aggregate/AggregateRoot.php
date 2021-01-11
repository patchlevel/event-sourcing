<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

use function end;
use function explode;
use function get_class;
use function method_exists;

abstract class AggregateRoot
{
    /** @var AggregateChanged[] */
    private array $uncommittedEvents = [];
    protected int $playhead = -1;

    final protected function __construct()
    {
    }

    abstract public function aggregateRootId(): string;

    protected function apply(AggregateChanged $event): void
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
     *
     * @return static
     */
    public static function createFromEventStream(array $stream): self
    {
        $self = new static();

        foreach ($stream as $message) {
            $self->playhead++;
            $self->handle($message);
        }

        return $self;
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
