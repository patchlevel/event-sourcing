<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

use Patchlevel\EventSourcing\Message\Message;
use Psr\Log\LoggerInterface;

use function array_shift;
use function sprintf;

final class DefaultEventBus implements EventBus
{
    /** @var array<Message<object>> */
    private array $queue;
    private bool $processing;

    public function __construct(
        private readonly Consumer $consumer,
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
                $this->consumer->consume($message);
            }
        } finally {
            $this->processing = false;

            $this->logger?->debug('EventBus: Finished processing queue.');
        }
    }

    /** @param iterable<object> $listeners */
    public static function create(iterable $listeners = []): self
    {
        return new self(DefaultConsumer::create($listeners));
    }
}
