<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Aggregate;

interface AggregateRootInterface
{
    public function aggregateRootId(): string;

    /**
     * @param list<object> $events
     */
    public function catchUp(array $events): void;

    /**
     * @return list<object>
     */
    public function releaseEvents(): array;

    /**
     * @param list<object>   $events
     * @param 0|positive-int $startPlayhead
     */
    public static function createFromEvents(array $events, int $startPlayhead = 0): static;

    public function playhead(): int;
}
