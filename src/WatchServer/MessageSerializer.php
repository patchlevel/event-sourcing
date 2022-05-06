<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\WatchServer;

use Patchlevel\EventSourcing\EventBus\Message;

interface MessageSerializer
{
    public function serialize(Message $message): string;

    public function deserialize(string $content): Message;
}
