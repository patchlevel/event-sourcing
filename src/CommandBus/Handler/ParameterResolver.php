<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus\Handler;

use Patchlevel\EventSourcing\Attribute\Inject;
use Psr\Container\ContainerInterface;
use ReflectionMethod;
use ReflectionParameter;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

/** @internal */
final class ParameterResolver
{
    /** @return iterable<mixed> */
    public static function resolve(ReflectionMethod $method, ContainerInterface|null $container = null): iterable
    {
        foreach ($method->getParameters() as $index => $parameter) {
            if ($index === 0) {
                continue; // skip first parameter (command)
            }

            if (!$container) {
                throw ServiceNotResolvable::missingContainer();
            }

            yield $container->get(self::serviceName($method, $parameter));
        }
    }

    private static function serviceName(ReflectionMethod $method, ReflectionParameter $parameter): string
    {
        $attributes = $parameter->getAttributes(Inject::class);

        if ($attributes !== []) {
            return $attributes[0]->newInstance()->service;
        }

        $reflectionType = $parameter->getType();

        if ($reflectionType === null) {
            throw ServiceNotResolvable::missingType($method->getDeclaringClass()->getName(), $parameter->getName());
        }

        $type = TypeResolver::create()->resolve($reflectionType);

        if (!$type instanceof ObjectType) {
            throw ServiceNotResolvable::typeNotObject($method->getDeclaringClass()->getName(), $parameter->getName());
        }

        return $type->getClassName();
    }
}
