<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus;

use Psr\Log\LoggerInterface;

use function array_shift;
use function sprintf;

final class DefaultCommandBus implements CommandBus
{
    /** @var array<object> */
    private array $queue;
    private bool $processing;

    public function __construct(
        private readonly HandlerProvider $handlerProvider,
        private readonly LoggerInterface|null $logger = null,
    ) {
        $this->queue = [];
        $this->processing = false;
    }

    public function dispatch(object $command): void
    {
        $this->logger?->debug(sprintf(
            'CommandBus: Add message "%s" to queue.',
            $command::class,
        ));

        $this->queue[] = $command;

        if ($this->processing) {
            $this->logger?->debug('CommandBus: Is already processing, dont start new processing.');

            return;
        }

        try {
            $this->processing = true;

            $this->logger?->debug('CommandBus: Start processing queue.');

            while ($command = array_shift($this->queue)) {
                $handler = $this->handlerProvider->handlerForCommand($command);

                ($handler->callable())($command);
            }
        } finally {
            $this->processing = false;

            $this->logger?->debug('CommandBus: Finished processing queue.');
        }
    }
}
