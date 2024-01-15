<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Outbox;

use Patchlevel\EventSourcing\EventBus\Message;

interface OutboxPublisher
{
    public function publish(Message $message): void;
}
