<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Message\Translator;

use Patchlevel\EventSourcing\Message\HeaderNotFound;
use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Store\ArchivedHeader;

final class OnlyArchivedEventTranslator implements Translator
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
