<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus\Handler;

use InvalidArgumentException;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use ReflectionClass;

final class CreateAggregateHandler
{
    /** @param class-string<AggregateRoot> $aggregateClass */
    public function __construct(
        private readonly RepositoryManager $repositoryManager,
        private readonly string $aggregateClass,
        private readonly string $methodName,
        private readonly ParameterResolver $parameterResolver,
    ) {
    }

    public function __invoke(object $command): void
    {
        $repository = $this->repositoryManager->get($this->aggregateClass);

        $reflection = new ReflectionClass($this->aggregateClass);
        $reflectionMethod = $reflection->getMethod($this->methodName);

        $aggregate = $reflectionMethod->invokeArgs(
            null,
            [...$this->parameterResolver->resolve($reflectionMethod, $command)],
        );

        if (!$aggregate instanceof AggregateRoot) {
            throw new InvalidArgumentException('create method must return an instance of AggregateRoot');
        }

        $repository->save($aggregate);
    }
}
