<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;

class EventBucket
{
    /** @var class-string<AggregateRoot> */
    private string $aggregateClass;
    private AggregateChanged $event;

    /**
     * @param class-string<AggregateRoot> $aggregateClass
     */
    public function __construct(string $aggregateClass, AggregateChanged $event)
    {
        $this->aggregateClass = $aggregateClass;
        $this->event = $event;
    }

    /**
     * @return class-string<AggregateRoot>
     */
    public function aggregateClass(): string
    {
        return $this->aggregateClass;
    }

    public function event(): AggregateChanged
    {
        return $this->event;
    }
}
