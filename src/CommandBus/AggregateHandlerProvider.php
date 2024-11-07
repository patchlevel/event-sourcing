<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Aggregate\AggregateRootId;
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\Attribute\HandledBy;
use Patchlevel\EventSourcing\Attribute\Id;
use Patchlevel\EventSourcing\Repository\RepositoryManager;
use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

final class AggregateHandlerProvider implements HandlerProvider
{
    public function __construct(
        private readonly RepositoryManager $repositoryManager,
    ) {
    }

    public function handlerForCommand(object $command): HandlerDescriptor
    {
        $aggregateClass = $this->aggregateClass($command::class);

        $reflectionClass = new ReflectionClass($aggregateClass);
        $repository = $this->repositoryManager->get($aggregateClass);

        foreach ($reflectionClass->getMethods() as $method) {
            $attributes = $method->getAttributes(Handle::class);

            if ($attributes === []) {
                continue;
            }

            $handleClass = $this->handleClass($attributes[0]->newInstance(), $method);

            if ($handleClass !== $command::class) {
                continue;
            }

            if ($method->isStatic()) {
                return new HandlerDescriptor(
                    static function (...$args) use ($method, $repository): void {
                        $aggregate = $method->invoke(null, ...$args);
                        $repository->save($aggregate);
                    },
                );
            }

            return new HandlerDescriptor(
                function (...$args) use ($method, $repository, $command): void {
                    $aggregateRootId = $this->aggregateRootId($command);
                    $aggregate = $repository->load($aggregateRootId);

                    $aggregate->{$method->getName()}(...$args);

                    $repository->save($aggregate);
                },
            );
        }

        throw new RuntimeException('No handler found for command ' . $commandClass);
    }

    /**
     * @param class-string $commandClass
     *
     * @return class-string<AggregateRoot>
     */
    private function aggregateClass(string $commandClass): string
    {
        $reflectionClass = new ReflectionClass($commandClass);
        $attributes = $reflectionClass->getAttributes(HandledBy::class);

        if ($attributes === []) {
            throw new RuntimeException('No handler found for command ' . $commandClass);
        }

        $handledBy = $attributes[0]->newInstance();

        return $handledBy->aggregateClass;
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

        throw new RuntimeException('No id found for aggregate ' . $reflectionClass->getName());
    }

    private function handleClass(Handle $handle, ReflectionMethod $reflectionMethod): string
    {
        if ($handle->commandClass !== null) {
            return $handle->commandClass;
        }

        $parameters = $reflectionMethod->getParameters();

        if ($parameters === []) {
            throw new RuntimeException('Invalid handler method ' . $reflectionMethod->getName());
        }

        return $parameters[0]->getType()->getName();
    }
}
