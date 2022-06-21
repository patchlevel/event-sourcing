<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Middleware;

use DateTimeImmutable;
use Patchlevel\EventSourcing\EventBus\Message;

final class UntilEventMiddleware implements Middleware
{
    private DateTimeImmutable $until;

    public function __construct(DateTimeImmutable $until)
    {
        $this->until = $until;
    }

    /**
     * @return list<Message>
     */
    public function __invoke(Message $message): array
    {
        $recordedOn = $message->recordedOn();

        if ($recordedOn < $this->until) {
            return [$message];
        }

        return [];
    }
}
