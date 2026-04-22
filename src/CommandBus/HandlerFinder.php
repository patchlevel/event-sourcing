<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus;

use Patchlevel\EventSourcing\Attribute\Handle;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\Type\UnionType;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

final class HandlerFinder
{
    private static TypeResolver $typeResolver;

    /**
     * @param class-string $classString
     *
     * @return iterable<int, HandlerReference>
     */
    public static function findInClass(string $classString): iterable
    {
        $reflectionClass = new ReflectionClass($classString);

        foreach ($reflectionClass->getMethods() as $reflectionMethod) {
            $handleAttributes = $reflectionMethod->getAttributes(Handle::class);

            if ($handleAttributes === []) {
                continue;
            }

            foreach ($handleAttributes as $attribute) {
                $handle = $attribute->newInstance();

                if ($handle->commandClass !== null) {
                    yield new HandlerReference(
                        $handle->commandClass,
                        $reflectionMethod->getName(),
                        $reflectionMethod->isStatic(),
                    );

                    continue;
                }

                foreach (self::guessHandledClasses($reflectionMethod) as $class) {
                    yield new HandlerReference(
                        $class,
                        $reflectionMethod->getName(),
                        $reflectionMethod->isStatic(),
                    );
                }
            }
        }
    }

    /** @return list<class-string> */
    private static function guessHandledClasses(ReflectionMethod $reflectionMethod): array
    {
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

        $type = self::typeResolver()->resolve($reflectionType);

        if ($type instanceof ObjectType) {
            /** @var class-string $className */
            $className = $type->getClassName();

            return [$className];
        }

        if ($type instanceof UnionType) {
            $types = [];

            foreach ($type->getTypes() as $unionType) {
                if (!$unionType instanceof ObjectType) {
                    throw InvalidHandleMethod::incompatibleType(
                        $reflectionMethod->getDeclaringClass()->getName(),
                        $reflectionMethod->getName(),
                    );
                }

                /** @var class-string $className */
                $className = $unionType->getClassName();

                $types[] = $className;
            }

            return $types;
        }

        throw InvalidHandleMethod::incompatibleType(
            $reflectionMethod->getDeclaringClass()->getName(),
            $reflectionMethod->getName(),
        );
    }

    private static function typeResolver(): TypeResolver
    {
        return self::$typeResolver ??= TypeResolver::create();
    }
}
