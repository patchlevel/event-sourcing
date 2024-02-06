<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Outbox;

use Patchlevel\EventSourcing\EventBus\EventBus;
use Patchlevel\EventSourcing\EventBus\Message;
use Psr\Log\LoggerInterface;

use function sprintf;

final class OutboxEventBus implements EventBus
{
    public function __construct(
        private readonly OutboxStore $store,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function dispatch(Message ...$messages): void
    {
        $this->store->saveOutboxMessage(...$messages);

        foreach ($messages as $message) {
            $this->logger?->debug(sprintf(
                'EventBus: Message "%s" added to queue.',
                $message->event()::class,
            ));
        }
    }
}
