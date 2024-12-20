<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus;

use Patchlevel\EventSourcing\CommandBus\Handler\HandlerFactory;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;

final class AggregateHandlerProvider implements HandlerProvider
{
    private bool $initialized = false;

    /** @var array<class-string, list<HandlerDescriptor>> */
    private array $handlers = [];

    public function __construct(
        private readonly AggregateRootRegistry $aggregateRootRegistry,
        private readonly HandlerFactory $handlerFactory,
    ) {
    }

    /**
     * @param class-string $commandClass
     *
     * @return iterable<HandlerDescriptor>
     */
    public function handlerForCommand(string $commandClass): iterable
    {
        if (!$this->initialized) {
            $this->initialize();
        }

        return $this->handlers[$commandClass] ?? [];
    }

    private function initialize(): void
    {
        foreach ($this->aggregateRootRegistry->aggregateClasses() as $aggregateClass) {
            $aggregateHandlerFinder = new AggregateHandlerFinder($aggregateClass);

            foreach ($aggregateHandlerFinder->createHandlers() as $handler) {
                $this->handlers[$handler->commandClass][] = new HandlerDescriptor(
                    $this->handlerFactory->createHandler(
                        $aggregateClass,
                        $handler->method,
                    ),
                );
            }

            foreach ($aggregateHandlerFinder->updateHandlers() as $handler) {
                $this->handlers[$handler->commandClass][] = new HandlerDescriptor(
                    $this->handlerFactory->updateHandler(
                        $aggregateClass,
                        $handler->method,
                    ),
                );
            }
        }

        $this->initialized = true;
    }
}
