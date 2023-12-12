<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Outbox;

use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\Store\OutboxStore;

final class StoreOutboxConsumer implements OutboxConsumer
{
    public function __construct(private OutboxStore $outboxStore, private EventBus $eventBus)
    {
    }

    public function consume(int|null $limit = null): void
    {
        $messages = $this->outboxStore->retrieveOutboxMessages($limit);

        foreach ($messages as $message) {
            $this->eventBus->dispatch($message);
            $this->outboxStore->markOutboxMessageConsumed($message);
        }
    }
}
