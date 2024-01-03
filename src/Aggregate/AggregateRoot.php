<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

interface AggregateRoot
{
    public function aggregateRootId(): AggregateRootId;

    /** @param iterable<object> $events */
    public function catchUp(iterable $events): void;

    /** @return list<object> */
    public function releaseEvents(): array;

    /**
     * @param iterable<object> $events
     * @param 0|positive-int   $startPlayhead
     */
    public static function createFromEvents(iterable $events, int $startPlayhead = 0): static;

    public function playhead(): int;
}
