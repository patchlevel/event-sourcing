<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus\Handler;

use Patchlevel\EventSourcing\Repository\RepositoryManager;
use Psr\Container\ContainerInterface;

final class DefaultHandlerFactory implements HandlerFactory
{
    public function __construct(
        private readonly RepositoryManager $repositoryManager,
        private readonly ContainerInterface|null $container = null,
    ) {
    }

    public function createHandler(string $aggregateClass, string $method): callable
    {
        return new CreateAggregateHandler(
            $this->repositoryManager,
            $aggregateClass,
            $method,
            $this->container,
        );
    }

    public function updateHandler(string $aggregateClass, string $method): callable
    {
        return new UpdateAggregateHandler(
            $this->repositoryManager,
            $aggregateClass,
            $method,
            $this->container,
        );
    }
}
