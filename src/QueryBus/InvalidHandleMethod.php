<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\QueryBus;

use InvalidArgumentException;

use function sprintf;

final class InvalidHandleMethod extends InvalidArgumentException
{
    public static function noParameters(string $class, string $method): self
    {
        return new self(sprintf('Query handling method "%s" in class "%s" has no parameters', $method, $class));
    }

    public static function incompatibleType(string $class, string $method): self
    {
        return new self(sprintf('Query handling method "%s" in class "%s" has no compatible type', $method, $class));
    }
}
