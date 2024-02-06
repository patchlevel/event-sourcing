<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Outbox;

use Patchlevel\EventSourcing\EventBus\Consumer;
use Patchlevel\EventSourcing\EventBus\Message;

final class EventBusPublisher implements OutboxPublisher
{
    public function __construct(
        private readonly Consumer $consumer,
    ) {
    }

    public function publish(Message $message): void
    {
        $this->consumer->consume($message);
    }
}
