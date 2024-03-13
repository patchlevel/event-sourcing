<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Message\Middleware;

use Patchlevel\EventSourcing\Message\HeaderNotFound;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\ArchivedHeader;

final class OnlyArchivedEventMiddleware implements Middleware
{
    /** @return list<Message> */
    public function __invoke(Message $message): array
    {
        try {
            $archivedHeader = $message->header(ArchivedHeader::class);

            if ($archivedHeader->archived) {
                return [$message];
            }
        } catch (HeaderNotFound) {
        }

        return [];
    }
}
