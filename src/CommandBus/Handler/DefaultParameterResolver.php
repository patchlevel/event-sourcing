<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus\Handler;

use Patchlevel\EventSourcing\Attribute\Inject;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use ReflectionMethod;
use ReflectionParameter;
use Symfony\Component\TypeInfo\Type\ObjectType;
use Symfony\Component\TypeInfo\TypeResolver\TypeResolver;

final class DefaultParameterResolver implements ParameterResolver
{
    public function __construct(
        private readonly ContainerInterface|null $container = null,
    ) {
    }

    public function resolve(ReflectionMethod $method, object $command): iterable
    {
        foreach ($method->getParameters() as $index => $parameter) {
            if ($index === 0) {
                yield $command; // first parameter is always the command

                continue;
            }

            if (!$this->container) {
                throw ServiceNotResolvable::missingContainer();
            }

            try {
                yield $this->container->get($this->serviceName($method, $parameter));
            } catch (ContainerExceptionInterface $exception) {
                throw ServiceNotResolvable::missingService(
                    $method->getDeclaringClass()->getName(),
                    $method->getName(),
                    $parameter->getName(),
                    $exception,
                );
            }
        }
    }

    private function serviceName(ReflectionMethod $method, ReflectionParameter $parameter): string
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
