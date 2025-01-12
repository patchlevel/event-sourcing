<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus;

use Patchlevel\EventSourcing\CommandBus\Handler\CreateAggregateHandler;
use Patchlevel\EventSourcing\CommandBus\Handler\DefaultParameterResolver;
use Patchlevel\EventSourcing\CommandBus\Handler\UpdateAggregateHandler;
use Patchlevel\EventSourcing\Metadata\AggregateRoot\AggregateRootRegistry;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Psr\Container\ContainerInterface;

final class AggregateHandlerProvider implements HandlerProvider
{
    private bool $initialized = false;

    /** @var array<class-string, list<HandlerDescriptor>> */
    private array $handlers = [];

    public function __construct(
        private readonly AggregateRootRegistry $aggregateRootRegistry,
        private readonly RepositoryManager $repositoryManager,
        private readonly ContainerInterface|null $container = null,
    ) {
    }

    /**
     * @param class-string $commandClass
     *
     * @return iterable<int, HandlerDescriptor>
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
            foreach (HandlerFinder::findInClass($aggregateClass) as $handler) {
                if ($handler->static) {
                    $this->handlers[$handler->commandClass][] = new HandlerDescriptor(
                        new CreateAggregateHandler(
                            $this->repositoryManager,
                            $aggregateClass,
                            $handler->method,
                            new DefaultParameterResolver($this->container),
                        ),
                    );

                    continue;
                }

                $this->handlers[$handler->commandClass][] = new HandlerDescriptor(
                    new UpdateAggregateHandler(
                        $this->repositoryManager,
                        $aggregateClass,
                        $handler->method,
                        new DefaultParameterResolver($this->container),
                    ),
                );
            }
        }

        $this->initialized = true;
    }
}
