<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Source;

use Generator;
use Patchlevel\EventSourcing\Pipeline\EventBucket;

use function count;

final class InMemorySource implements Source
{
    /** @var list<EventBucket> */
    private array $events;

    /**
     * @param list<EventBucket> $events
     */
    public function __construct(array $events)
    {
        $this->events = $events;
    }

    /**
     * @return Generator<EventBucket>
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
