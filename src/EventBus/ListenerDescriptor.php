<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

use Closure;
use ReflectionFunction;

use function method_exists;

final class ListenerDescriptor
{
    private readonly Closure $callable;
    private readonly string $name;

    public function __construct(callable $callable)
    {
        $callable = $callable(...);

        $this->callable = $callable;

        $reflectionFunction = new ReflectionFunction($callable);

        if (method_exists($reflectionFunction, 'isAnonymous') && $reflectionFunction->isAnonymous()) {
            $this->name = 'Closure';

            return;
        }

        $callable = $reflectionFunction->getClosureThis();

        if (!$callable) {
            $class = $reflectionFunction->getClosureCalledClass();

            $this->name = ($class ? $class->name . '::' : '') . $reflectionFunction->name;

            return;
        }

        $this->name = $callable::class . '::' . $reflectionFunction->name;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function callable(): callable
    {
        return $this->callable;
    }
}
