<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Middleware;

use Patchlevel\EventSourcing\EventBus\Message;

final class OnlyArchivedEventMiddleware implements Middleware
{
    /** @return list<Message> */
    public function __invoke(Message $message): array
    {
        if ($message->archived()) {
            return [$message];
        }

        return [];
    }
}
