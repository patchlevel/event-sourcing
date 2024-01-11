<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

use Psr\EventDispatcher\EventDispatcherInterface;

final class Psr14EventBus implements EventBus
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function dispatch(Message ...$messages): void
    {
        foreach ($messages as $message) {
            $this->eventDispatcher->dispatch($message);
        }
    }
}
