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
    /**
     * @param class-string<AggregateRoot> $aggregateClass
     *
     * @return iterable<AggregateHandler>
     */
    public static function find(string $aggregateClass): iterable
    {
        $typeResolver = TypeResolver::create();
        $reflectionClass = new ReflectionClass($aggregateClass);

        foreach ($reflectionClass->getMethods() as $reflectionMethod) {
            $handleAttributes = $reflectionMethod->getAttributes(Handle::class);

            if ($handleAttributes === []) {
                continue;
            }

            $handle = $handleAttributes[0]->newInstance();

            if ($handle->commandClass !== null) {
                yield new AggregateHandler(
                    $handle->commandClass,
                    $reflectionMethod->getName(),
                    $reflectionMethod->isStatic(),
                );

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

            $commandClass = $type->getClassName();

            if (!class_exists($commandClass)) {
                throw InvalidHandleMethod::incompatibleType(
                    $reflectionMethod->getDeclaringClass()->getName(),
                    $reflectionMethod->getName(),
                );
            }

            yield new AggregateHandler(
                $commandClass,
                $reflectionMethod->getName(),
                $reflectionMethod->isStatic(),
            );
        }
    }
}
