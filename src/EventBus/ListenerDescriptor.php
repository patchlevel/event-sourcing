<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\EventBus;

use Closure;
use ReflectionFunction;

final class ListenerDescriptor
{
    private readonly Closure $callable;
    private readonly string $name;

    public function __construct(callable $callable)
    {
        $callable = $callable(...);

        $this->callable = $callable;

        $r = new ReflectionFunction($callable);

        if ($r->isAnonymous()) {
            $this->name = 'Closure';
        } elseif (!$callable = $r->getClosureThis()) {
            $class = $r->getClosureCalledClass();

            $this->name = ($class ? $class->name . '::' : '') . $r->name;
        } else {
            $this->name = $callable::class . '::' . $r->name;
        }
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
