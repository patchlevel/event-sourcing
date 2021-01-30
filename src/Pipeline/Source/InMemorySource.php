<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Source;

use Generator;
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

use function count;

class InMemorySource implements Source
{
    /** @var list<AggregateChanged> */
    private array $events;

    /**
     * @param list<AggregateChanged> $events
     */
    public function __construct(array $events)
    {
        $this->events = $events;
    }

    /**
     * @return Generator<AggregateChanged>
     */
    public function load(): Generator
    {
        foreach ($this->events as $event) {
            yield $event;
        }
    }

    public function count(): int
    {
        return count($this->events);
    }
}
