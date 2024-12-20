<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Handle;
use Patchlevel\EventSourcing\Attribute\HandledBy;
use Patchlevel\EventSourcing\CommandBus\Handler\HandlerFactory;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

use function array_key_exists;
use function is_a;

final class AggregateHandlerProvider implements HandlerProvider
{
    /** @var array<class-string, HandlerDescriptor> */
    private array $handlers = [];

    private readonly TypeResolver $typeResolver;

    public function __construct(
        private readonly HandlerFactory $handlerFactory,
    ) {
        $this->typeResolver = TypeResolver::create();
    }

    /**
     * @param class-string $commandClass
     *
     * @throws HandlerNotFound
     */
    public function handlerForCommand(string $commandClass): HandlerDescriptor
    {
        if (array_key_exists($commandClass, $this->handlers)) {
            return $this->handlers[$commandClass];
        }

        $aggregateClass = $this->aggregateClass($commandClass);

        $reflectionClass = new ReflectionClass($aggregateClass);

        foreach ($reflectionClass->getMethods() as $method) {
            if (!$this->canHandle($method, $commandClass)) {
                continue;
            }

            if ($method->isStatic()) {
                $this->handlers[$commandClass] = new HandlerDescriptor($this->handlerFactory->createHandler(
                    $aggregateClass,
                    $method->getName(),
                ));
            } else {
                $this->handlers[$commandClass] = new HandlerDescriptor($this->handlerFactory->updateHandler(
                    $aggregateClass,
                    $method->getName(),
                ));
            }

            return $this->handlers[$commandClass];
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

    private function canHandle(ReflectionMethod $reflectionMethod, string $commandClass): bool
    {
        $handleAttributes = $reflectionMethod->getAttributes(Handle::class);

        if ($handleAttributes === []) {
            return false;
        }

        $parameters = $reflectionMethod->getParameters();

        if ($parameters === []) {
            throw InvalidHandleMethod::noParameters(
                $reflectionMethod->getDeclaringClass()->getName(),
                $reflectionMethod->getName(),
            );
        }

        $reflectionType = $parameters[0]->getType();

        if ($reflectionType === null) {
            throw InvalidHandleMethod::incompatibleType(
                $reflectionMethod->getDeclaringClass()->getName(),
                $reflectionMethod->getName(),
            );
        }

        $type = $this->typeResolver->resolve($reflectionType);

        if (!$type instanceof ObjectType) {
            throw InvalidHandleMethod::incompatibleType(
                $reflectionMethod->getDeclaringClass()->getName(),
                $reflectionMethod->getName(),
            );
        }

        $handle = $handleAttributes[0]->newInstance();
        $handleClassName = $handle->commandClass ?: $type->getClassName();

        if ($handleClassName === $commandClass) {
            return true;
        }

        return is_a($commandClass, $handleClassName, true);
    }
}
