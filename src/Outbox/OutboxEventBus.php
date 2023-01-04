<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Outbox;

use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\EventBus\Message;

final class OutboxEventBus implements EventBus
{
    public function __construct(
        private readonly OutboxStore $store
    ) {
    }

    public function dispatch(Message ...$messages): void
    {
        $this->store->saveOutboxMessage(...$messages);
    }
}
