<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\Attribute\HandledBy;
use Patchlevel\EventSourcing\CommandBus\Handler\DefaultHandlerFactory;
use ReflectionClass;
use ReflectionMethod;

final class AggregateHandlerProvider implements HandlerProvider
{
    public function __construct(
        private readonly DefaultHandlerFactory $handlerFactory,
    ) {
    }

    /**
     * @param class-string $commandClass
     *
     * @throws HandlerNotFound
     */
    public function handlerForCommand(string $commandClass): HandlerDescriptor
    {
        $aggregateClass = $this->aggregateClass($commandClass);

        $reflectionClass = new ReflectionClass($aggregateClass);

        foreach ($reflectionClass->getMethods() as $method) {
            $attributes = $method->getAttributes(Handle::class);

            if ($attributes === []) {
                continue;
            }

            $handleClass = $this->handleClass($attributes[0]->newInstance(), $method);

            if ($handleClass !== $commandClass) {
                continue;
            }

            if ($method->isStatic()) {
                return new HandlerDescriptor($this->handlerFactory->createHandler($aggregateClass, $method->getName()));
            }

            return new HandlerDescriptor($this->handlerFactory->updateHandler($aggregateClass, $method->getName()));
        }

        throw new HandlerNotFound($commandClass);
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
            throw new HandlerNotFound($commandClass);
        }

        $handledBy = $attributes[0]->newInstance();

        return $handledBy->aggregateClass;
    }

    private function handleClass(Handle $handle, ReflectionMethod $reflectionMethod): string|null
    {
        if ($handle->commandClass !== null) {
            return $handle->commandClass;
        }

        $parameters = $reflectionMethod->getParameters();

        if ($parameters === []) {
            return null;
        }

        return $parameters[0]->getType()->getName();
    }
}
