<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus\Handler;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Aggregate\AggregateRootId;
use Patchlevel\EventSourcing\Attribute\Id;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use ReflectionClass;

final class UpdateAggregateHandler
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
        $aggregateRootId = $this->aggregateRootId($command);
        $repository = $this->repositoryManager->get($this->aggregateClass);

        $aggregate = $repository->load($aggregateRootId);

        $aggregate->{$this->methodName}($command);

        $repository->save($aggregate);
    }

    private function aggregateRootId(object $command): AggregateRootId
    {
        $reflectionClass = new ReflectionClass($command);

        foreach ($reflectionClass->getProperties() as $property) {
            $attributes = $property->getAttributes(Id::class);

            if ($attributes === []) {
                continue;
            }

            return $property->getValue($command);
        }

        throw new AggregateIdNotFound($command::class);
    }
}
