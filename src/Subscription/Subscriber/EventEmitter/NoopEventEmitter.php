<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber\EventEmitter;

final class NoopEventEmitter implements EventEmitter
{
    /** @param list<object> $events */
    public function emit(array $events): void
    {
    }

    /** @param list<object> $events */
    public function linkTo(string $streamName, array $events): void
    {
    }
}
