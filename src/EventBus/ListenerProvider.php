<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

interface ListenerProvider
{
    /** @return iterable<ListenerDescriptor> */
    public function listenersForEvent(object $event): iterable;
}
