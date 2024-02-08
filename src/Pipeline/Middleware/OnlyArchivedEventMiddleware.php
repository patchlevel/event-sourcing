<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Middleware;

use Patchlevel\EventSourcing\EventBus\HeaderNotFound;
use Patchlevel\EventSourcing\EventBus\Message;

final class OnlyArchivedEventMiddleware implements Middleware
{
    /** @return list<Message> */
    public function __invoke(Message $message): array
    {
        try {
            $archived = $message->archived();

            if ($archived) {
                return [$message];
            }
        } catch (HeaderNotFound) {
        }

        return [];
    }
}
