<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Outbox;

use Patchlevel\EventSourcing\EventBus\Message;

interface OutboxStore
{
    public function saveOutboxMessage(Message ...$messages): void;

    /** @return list<Message> */
    public function retrieveOutboxMessages(int|null $limit = null): array;

    public function markOutboxMessageConsumed(Message ...$messages): void;

    public function countOutboxMessages(): int;
}
