<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus\Handler;

use InvalidArgumentException;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Id;
use Patchlevel\EventSourcing\Identifier\Identifier;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use ReflectionClass;

final class UpdateAggregateHandler
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
        $aggregateRootId = $this->aggregateRootId($command);
        $repository = $this->repositoryManager->get($this->aggregateClass);

        $aggregate = $repository->load($aggregateRootId);

        $reflection = new ReflectionClass($this->aggregateClass);
        $reflectionMethod = $reflection->getMethod($this->methodName);

        $reflectionMethod->invokeArgs(
            $aggregate,
            [...$this->parameterResolver->resolve($reflectionMethod, $command)],
        );

        $repository->save($aggregate);
    }

    private function aggregateRootId(object $command): Identifier
    {
        $reflectionClass = new ReflectionClass($command);

        foreach ($reflectionClass->getProperties() as $property) {
            $attributes = $property->getAttributes(Id::class);

            if ($attributes === []) {
                continue;
            }

            $value = $property->getValue($command);

            if (!$value instanceof Identifier) {
                throw new InvalidArgumentException('Id property must be an instance of AggregateRootId');
            }

            return $value;
        }

        throw new AggregateIdNotFound($command::class);
    }
}
