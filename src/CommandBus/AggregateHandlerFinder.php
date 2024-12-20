<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Attribute\Handle;
use ReflectionClass;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

use function class_exists;

/** @internal */
final class AggregateHandlerFinder
{
    /** @var list<AggregateHandler> */
    private array $createHandlers = [];

    /** @var list<AggregateHandler> */
    private array $updateHandlers = [];

    /** @param class-string<AggregateRoot> $aggregateClass */
    public function __construct(string $aggregateClass)
    {
        $typeResolver = TypeResolver::create();
        $reflectionClass = new ReflectionClass($aggregateClass);

        foreach ($reflectionClass->getMethods() as $reflectionMethod) {
            $handleAttributes = $reflectionMethod->getAttributes(Handle::class);

            if ($handleAttributes === []) {
                continue;
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

            $type = $typeResolver->resolve($reflectionType);

            if (!$type instanceof ObjectType) {
                throw InvalidHandleMethod::incompatibleType(
                    $reflectionMethod->getDeclaringClass()->getName(),
                    $reflectionMethod->getName(),
                );
            }

            $handle = $handleAttributes[0]->newInstance();
            $commandClass = $handle->commandClass ?: $type->getClassName();

            if (!class_exists($commandClass)) {
                throw InvalidHandleMethod::incompatibleType(
                    $reflectionMethod->getDeclaringClass()->getName(),
                    $reflectionMethod->getName(),
                );
            }

            if ($reflectionMethod->isStatic()) {
                $this->createHandlers[] = new AggregateHandler(
                    $reflectionMethod->getName(),
                    $commandClass,
                );
            } else {
                $this->updateHandlers[] = new AggregateHandler(
                    $reflectionMethod->getName(),
                    $commandClass,
                );
            }
        }
    }

    /** @return iterable<AggregateHandler> */
    public function createHandlers(): iterable
    {
        return $this->createHandlers;
    }

    /** @return iterable<AggregateHandler> */
    public function updateHandlers(): iterable
    {
        return $this->updateHandlers;
    }
}
