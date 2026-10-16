<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\DecisionModel;

use Patchlevel\EventSourcing\CommandBus\Handler\ParameterResolver;
use Patchlevel\EventSourcing\CommandBus\Handler\ServiceNotResolvable;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Passes a new decision to every command handler parameter of type Decision.
 *
 * @experimental
 */
final class DecisionParameterResolver implements ParameterResolver
{
    public function __construct(
        private readonly DecisionFactory $decisionFactory,
    ) {
    }

    /** @return iterable<int, mixed> */
    public function resolve(ReflectionMethod $method, object $command): iterable
    {
        foreach ($method->getParameters() as $index => $parameter) {
            if ($index === 0) {
                yield $command; // first parameter is always the command

                continue;
            }

            $type = $parameter->getType();

            if (!$type instanceof ReflectionNamedType || $type->getName() !== Decision::class) {
                throw ServiceNotResolvable::unsupportedParameter(
                    $method->getDeclaringClass()->getName(),
                    $method->getName(),
                    $parameter->getName(),
                );
            }

            yield $this->decisionFactory->begin();
        }
    }
}
