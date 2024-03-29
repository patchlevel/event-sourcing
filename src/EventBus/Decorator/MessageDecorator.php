<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus\Decorator;

use Patchlevel\EventSourcing\EventBus\Message;

interface MessageDecorator
{
    public function __invoke(Message $message): Message;
}
