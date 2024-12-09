<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus;

use InvalidArgumentException;

use function sprintf;

final class InvalidHandleMethod extends InvalidArgumentException
{
    public static function noParameters(string $aggregateClass, string $method): self
    {
        return new self(sprintf('Method "%s" in aggregate "%s" has no parameters', $method, $aggregateClass));
    }

    public static function noType(string $aggregateClass, string $method): self
    {
        return new self(sprintf('Method "%s" in aggregate "%s" has no compatible type', $method, $aggregateClass));
    }
}
