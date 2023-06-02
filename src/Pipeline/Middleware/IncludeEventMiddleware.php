<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Middleware;

use Patchlevel\EventSourcing\EventBus\Message;

final class IncludeEventMiddleware implements Middleware
{
    /** @param list<class-string> $classes */
    public function __construct(
        private array $classes,
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
