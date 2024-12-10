<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus\Handler;

use Patchlevel\EventSourcing\Attribute\Inject;
use Psr\Container\ContainerInterface;
use ReflectionMethod;
use RuntimeException;
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

            $attributes = $parameter->getAttributes(Inject::class);

            if ($attributes === []) {
                throw new RuntimeException('missing inject attribute');
            }

            $serviceName = $attributes[0]->newInstance()->service;

            if ($serviceName === null) {
                $reflectionType = $parameter->getType();

                if ($reflectionType === null) {
                    throw new RuntimeException('missing type hint');
                }

                $type = TypeResolver::create()->resolve($reflectionType);

                if (!$type instanceof ObjectType) {
                    throw new RuntimeException('type hint must be object');
                }

                $serviceName = $type->getClassName();
            }

            if (!$container) {
                throw new RuntimeException('missing inject attribute');
            }

            yield $container->get($serviceName);
        }
    }
}
