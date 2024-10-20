<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository\MessageDecorator;

use Patchlevel\EventSourcing\Message\Message;

interface MessageDecorator
{
    public function __invoke(Message $message): Message;
}
