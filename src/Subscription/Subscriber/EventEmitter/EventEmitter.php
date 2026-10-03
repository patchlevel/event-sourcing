<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber\EventEmitter;

interface EventEmitter
{
    /**
     * Emit events into the subscription's own projection stream.
     *
     * @param list<object> $events
     */
    public function emit(array $events): void;

    /**
     * Emit events into the given stream.
     *
     * @param list<object> $events
     */
    public function linkTo(string $streamName, array $events): void;
}
