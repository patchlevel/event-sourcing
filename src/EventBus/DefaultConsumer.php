<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

use Patchlevel\EventSourcing\Message\Message;
use Psr\Log\LoggerInterface;

use function sprintf;

final class DefaultConsumer implements Consumer
{
    public function __construct(
        private readonly ListenerProvider $listenerProvider,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function consume(Message $message): void
    {
        $eventClass = $message->event()::class;

        $this->logger?->debug(sprintf(
            'EventBus: Consume message "%s".',
            $eventClass,
        ));

        $listeners = $this->listenerProvider->listenersForEvent($eventClass);

        foreach ($listeners as $listener) {
            $this->logger?->info(sprintf(
                'EventBus: Listener "%s" consume message with event "%s".',
                $listener->name(),
                $eventClass,
            ));

            ($listener->callable())($message);
        }
    }

    /** @param iterable<object> $listeners */
    public static function create(iterable $listeners = []): self
    {
        return new self(new AttributeListenerProvider($listeners));
    }
}
