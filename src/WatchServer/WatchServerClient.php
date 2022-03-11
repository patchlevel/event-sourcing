<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\WatchServer;

use Patchlevel\EventSourcing\EventBus\Message;

interface WatchServerClient
{
    public function send(Message $message): void;
}
