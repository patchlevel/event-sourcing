<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Message\Translator;

use Patchlevel\EventSourcing\Message\Message;

final class IncludeEventWithHeaderTranslator implements Translator
{
    /** @param class-string $header */
    public function __construct(
        private readonly string $header,
    ) {
    }

    /** @return list<Message> */
    public function __invoke(Message $message): array
    {
        if ($message->hasHeader($this->header)) {
            return [$message];
        }

        return [];
    }
}
