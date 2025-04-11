<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\QueryBus;

use Patchlevel\EventSourcing\Attribute\Answer;
use ReflectionClass;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

final class HandlerFinder
{
    /**
     * @param class-string $classString
     *
     * @return iterable<int, HandlerReference>
     */
    public static function findInClass(string $classString): iterable
    {
        $typeResolver = TypeResolver::create();
        $reflectionClass = new ReflectionClass($classString);

        foreach ($reflectionClass->getMethods() as $reflectionMethod) {
            $answerAttributes = $reflectionMethod->getAttributes(Answer::class);

            if ($answerAttributes === []) {
                continue;
            }

            $answer = $answerAttributes[0]->newInstance();

            if ($answer->queryClass !== null) {
                yield new HandlerReference(
                    $answer->queryClass,
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

            /** @var class-string $commandClass */
            $commandClass = $type->getClassName();

            yield new HandlerReference(
                $commandClass,
                $reflectionMethod->getName(),
                $reflectionMethod->isStatic(),
            );
        }
    }
}
