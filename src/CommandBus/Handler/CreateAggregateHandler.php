<?php

namespace Patchlevel\EventSourcing\CommandBus\Handler;

use Patchlevel\EventSourcing\Repository\RepositoryManager;

final class CreateAggregateHandler
{
    /** @param class-string $aggregateClass */
    public function __construct(
        private readonly RepositoryManager $repositoryManager,
        private readonly string $aggregateClass,
        private readonly string $methodName,
    )
    {
    }

    public function __invoke(object $command): void
    {
        $repository = $this->repositoryManager->get($this->aggregateClass);

        $aggregate = $this->aggregateClass::{$this->methodName}($command);

        $repository->save($aggregate);
    }
}