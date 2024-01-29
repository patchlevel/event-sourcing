<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

interface ListenerProvider
{
    /**
     * @param class-string $eventClass
     *
     * @return iterable<ListenerDescriptor>
     */
    public function listenersForEvent(string $eventClass): iterable;
}
