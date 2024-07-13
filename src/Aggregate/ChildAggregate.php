<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

interface ChildAggregate
{
    /** @param iterable<object> $events */
    public function catchUp(iterable $events): void;

    public function playhead(): int;
}
