<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Middleware;

use DateTimeImmutable;
use Patchlevel\EventSourcing\EventBus\Message;

final class UntilEventMiddleware implements Middleware
{
    public function __construct(private DateTimeImmutable $until)
    {
    }

    /** @return list<Message> */
    public function __invoke(Message $message): array
    {
        $recordedOn = $message->recordedOn();

        if ($recordedOn < $this->until) {
            return [$message];
        }

        return [];
    }
}
