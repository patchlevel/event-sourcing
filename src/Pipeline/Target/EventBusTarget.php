<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Target;

use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\Message\Message;

final class EventBusTarget implements Target
{
    public function __construct(
        private readonly EventBus $eventBus,
    ) {
    }

    public function save(Message ...$message): void
    {
        $this->eventBus->dispatch(...$message);
    }
}
