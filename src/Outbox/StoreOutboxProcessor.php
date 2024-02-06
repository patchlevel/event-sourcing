<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Outbox;

final class StoreOutboxProcessor implements OutboxProcessor
{
    public function __construct(
        private readonly OutboxStore $store,
        private readonly OutboxPublisher $publisher,
    ) {
    }

    public function process(int|null $limit = null): void
    {
        $messages = $this->store->retrieveOutboxMessages($limit);

        foreach ($messages as $message) {
            $this->publisher->publish($message);
            $this->store->markOutboxMessageConsumed($message);
        }
    }
}
