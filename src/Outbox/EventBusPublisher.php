<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Outbox;

use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\EventBus\Message;

final class EventBusPublisher implements OutboxPublisher
{
    public function __construct(
        private readonly EventBus $eventBus,
    ) {
    }

    public function publish(Message $message): void
    {
        $this->eventBus->dispatch($message);
    }
}
