<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Target;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

class InMemoryTarget implements Target
{
    /** @var list<AggregateChanged> */
    private array $events = [];

    public function save(AggregateChanged $event): void
    {
        $this->events[] = $event;
    }

    /**
     * @return list<AggregateChanged>
     */
    public function events(): array
    {
        return $this->events;
    }
}
