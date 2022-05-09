<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

interface MessageDecorator
{
    public function __invoke(Message $message): Message;
}
