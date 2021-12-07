<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;

class EventBucket
{
    /** @var class-string<AggregateRoot> */
    private string $aggregateClass;
    private int $index;

    /** @var AggregateChanged<array<string, mixed>> */
    private AggregateChanged $event;

    /**
     * @param class-string<AggregateRoot>            $aggregateClass
     * @param AggregateChanged<array<string, mixed>> $event
     */
    public function __construct(string $aggregateClass, int $index, AggregateChanged $event)
    {
        $this->aggregateClass = $aggregateClass;
        $this->index = $index;
        $this->event = $event;
    }

    /**
     * @return class-string<AggregateRoot>
     */
    public function aggregateClass(): string
    {
        return $this->aggregateClass;
    }

    public function index(): int
    {
        return $this->index;
    }

    /**
     * @return AggregateChanged<array<string, mixed>>
     */
    public function event(): AggregateChanged
    {
        return $this->event;
    }
}
