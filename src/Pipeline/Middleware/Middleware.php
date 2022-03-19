<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Middleware;

use Patchlevel\EventSourcing\EventBus\Message;

interface Middleware
{
    /**
     * @return list<Message>
     */
    public function __invoke(Message $message): array;
}
