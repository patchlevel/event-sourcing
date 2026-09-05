<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus;

use Patchlevel\EventSourcing\Message\Context\MessageContext;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

use function count;
use function is_array;
use function iterator_to_array;

final class SyncCommandBus implements CommandBus
{
    private readonly HandlerProvider $handlerProvider;

    /** @param iterable<HandlerProvider>|HandlerProvider $handlerProviders */
    public function __construct(
        iterable|HandlerProvider $handlerProviders,
        private readonly LoggerInterface|null $logger = null,
        private readonly MessageContext|null $messageContext = null,
    ) {
        if (!$handlerProviders instanceof HandlerProvider) {
            $this->handlerProvider = new ChainHandlerProvider($handlerProviders);
        } else {
            $this->handlerProvider = $handlerProviders;
        }
    }

    /** @throws HandlerNotFound */
    public function dispatch(object $command): void
    {
        $this->logger?->debug('CommandBus: dispatch command', ['command' => $command::class]);

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

        // seed the correlation id once per command, so that all messages
        // recorded while handling this command share the same correlation id
        $this->messageContext?->push(
            $this->messageContext->causationId(),
            $this->messageContext->correlationId() ?? Uuid::uuid7()->toString(),
        );

        try {
            ($handlers[0]->callable())($command);
        } finally {
            $this->messageContext?->pop();
        }
    }

    public static function createForAggregateHandlers(
        AggregateRootRegistry $aggregateRootRegistry,
        RepositoryManager $repositoryManager,
        ContainerInterface|null $container = null,
        LoggerInterface|null $logger = null,
        MessageContext|null $messageContext = null,
    ): self {
        return new self(
            new AggregateHandlerProvider(
                $aggregateRootRegistry,
                $repositoryManager,
                $container,
            ),
            $logger,
            $messageContext,
        );
    }
}
