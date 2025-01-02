<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus;

use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

use function array_shift;
use function count;
use function is_array;
use function iterator_to_array;
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

    /** @throws HandlerNotFound */
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
                $handlers = $this->handlerProvider->handlerForCommand($command::class);

                if (!is_array($handlers)) {
                    $handlers = iterator_to_array($handlers);
                }

                $count = count($handlers);

                if ($count === 0) {
                    throw new HandlerNotFound($command::class);
                }

                if ($count > 1) {
                    throw new MultipleHandlersFound($command::class);
                }

                ($handlers[0]->callable())($command);
            }
        } finally {
            $this->processing = false;

            $this->logger?->debug('CommandBus: Finished processing queue.');
        }
    }

    public static function createForAggregateHandlers(
        AggregateRootRegistry $aggregateRootRegistry,
        RepositoryManager $repositoryManager,
        ContainerInterface|null $container = null,
        LoggerInterface|null $logger = null,
    ): self {
        return new self(
            new AggregateHandlerProvider(
                $aggregateRootRegistry,
                $repositoryManager,
                $container,
            ),
            $logger,
        );
    }
}
