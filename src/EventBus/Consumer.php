<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

use Patchlevel\EventSourcing\Message\Message;

interface Consumer
{
    public function consume(Message $message): void;
}
