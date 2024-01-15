<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

use Psr\Log\LoggerInterface;

use function array_shift;
use function sprintf;

final class DefaultEventBus implements EventBus
{
    /** @var array<Message<object>> */
    private array $queue;
    private bool $processing;

    public function __construct(
        private readonly ListenerProvider $listenerProvider,
        private readonly LoggerInterface|null $logger = null,
    ) {
        $this->queue = [];
        $this->processing = false;
    }

    public function dispatch(Message ...$messages): void
    {
        foreach ($messages as $message) {
            $this->logger?->debug(sprintf(
                'EventBus: Add message "%s" to queue.',
                $message->event()::class,
            ));

            $this->queue[] = $message;
        }

        if ($this->processing) {
            $this->logger?->debug('EventBus: Is already processing, dont start new processing.');

            return;
        }

        try {
            $this->processing = true;

            $this->logger?->debug('EventBus: Start processing queue.');

            while ($message = array_shift($this->queue)) {
                $this->logger?->debug(sprintf(
                    'EventBus: Dispatch message "%s" to listeners.',
                    $message->event()::class,
                ));

                $listeners = $this->listenerProvider->listenersForEvent($message->event());

                foreach ($listeners as $listener) {
                    $this->logger?->info(sprintf(
                        'EventBus: Dispatch message with event "%s" to listener "%s".',
                        $message->event()::class,
                        $listener->name(),
                    ));

                    ($listener->callable())($message);
                }
            }
        } finally {
            $this->processing = false;

            $this->logger?->debug('EventBus: Finished processing queue.');
        }
    }

    /** @param iterable<object> $listeners */
    public static function create(iterable $listeners = []): self
    {
        return new self(new AttributeListenerProvider($listeners));
    }
}
