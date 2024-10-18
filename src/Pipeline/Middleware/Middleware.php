<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Middleware;

use Patchlevel\EventSourcing\Message\Message;

interface Middleware
{
    /** @return list<Message> */
    public function __invoke(Message $message): array;
}
