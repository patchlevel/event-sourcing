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
        $this->callable = $callable(...);
        $this->name = self::closureName($this->callable);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function callable(): callable
    {
        return $this->callable;
    }

    private static function closureName(Closure $closure): string
    {
        $reflectionFunction = new ReflectionFunction($closure);

        if (method_exists($reflectionFunction, 'isAnonymous') && $reflectionFunction->isAnonymous()) {
            return 'Closure';
        }

        $closureThis = $reflectionFunction->getClosureThis();

        if (!$closureThis) {
            $class = $reflectionFunction->getClosureCalledClass();

            return ($class ? $class->name . '::' : '') . $reflectionFunction->name;
        }

        return $closureThis::class . '::' . $reflectionFunction->name;
    }
}
