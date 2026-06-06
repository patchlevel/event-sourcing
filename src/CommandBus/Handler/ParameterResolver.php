<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus\Handler;

use ReflectionMethod;

interface ParameterResolver
{
    /** @return iterable<int, mixed> */
    public function resolve(ReflectionMethod $method, object $command): iterable;
}
