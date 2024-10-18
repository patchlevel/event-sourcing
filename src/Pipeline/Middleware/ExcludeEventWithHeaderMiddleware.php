<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Middleware;

use Patchlevel\EventSourcing\Message\Message;

final class ExcludeEventWithHeaderMiddleware implements Middleware
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
            return [];
        }

        return [$message];
    }
}
