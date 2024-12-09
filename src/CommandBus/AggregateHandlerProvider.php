<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\Attribute\HandledBy;
use Patchlevel\EventSourcing\CommandBus\Handler\HandlerFactory;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

final class AggregateHandlerProvider implements HandlerProvider
{
    public function __construct(
        private readonly HandlerFactory $handlerFactory,
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
            throw new MissingHandledBy($commandClass);
        }

        $handledBy = $attributes[0]->newInstance();

        return $handledBy->aggregateClass;
    }

    private function handleClass(Handle $handle, ReflectionMethod $reflectionMethod): string|null
    {
        $parameters = $reflectionMethod->getParameters();

        if ($parameters === []) {
            throw InvalidHandleMethod::noParameters(
                $reflectionMethod->getDeclaringClass()->getName(),
                $reflectionMethod->getName(),
            );
        }

        if ($handle->commandClass !== null) {
            return $handle->commandClass;
        }

        $type = $parameters[0]->getType();

        if ($type instanceof ReflectionNamedType) {
            return $type->getName();
        }

        throw InvalidHandleMethod::noType(
            $reflectionMethod->getDeclaringClass()->getName(),
            $reflectionMethod->getName(),
        );
    }
}
