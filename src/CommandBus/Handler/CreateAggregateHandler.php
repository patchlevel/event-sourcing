<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus\Handler;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Repository\RepositoryManager;

final class CreateAggregateHandler
{
    /** @param class-string<AggregateRoot> $aggregateClass */
    public function __construct(
        private readonly RepositoryManager $repositoryManager,
        private readonly string $aggregateClass,
        private readonly string $methodName,
    ) {
    }

    public function __invoke(object $command): void
    {
        $repository = $this->repositoryManager->get($this->aggregateClass);

        $aggregate = $this->aggregateClass::{$this->methodName}($command);

        $repository->save($aggregate);
    }
}
