<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Patchlevel\EventSourcing\EventBus\Message;

interface OutboxStore extends TransactionStore
{
    public function saveOutboxMessage(Message ...$messages): void;

    /**
     * @return list<Message>
     */
    public function retrieveOutboxMessages(?int $limit = null): array;

    public function markOutboxMessageConsumed(Message ...$messages): void;

    public function countOutboxMessages(): int;
}
