<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Message\Translator;

use Patchlevel\EventSourcing\Message\Message;

final class IncludeEventTranslator implements Translator
{
    /** @param list<class-string> $classes */
    public function __construct(
        private readonly array $classes,
    ) {
    }

    /** @return list<Message> */
    public function __invoke(Message $message): array
    {
        foreach ($this->classes as $class) {
            if ($message->event() instanceof $class) {
                return [$message];
            }
        }

        return [];
    }
}
