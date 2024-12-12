<?php

namespace Patchlevel\EventSourcing\CommandBus\Handler;

use RuntimeException;

final class ServiceNotResolvable extends RuntimeException
{
    public static function missingType(string $class, $propertyName): self
    {
        return new self(sprintf('Missing type for property "%s" in class "%s"', $propertyName, $class));
    }

    public static function typeNotObject(string $class, $propertyName): self
    {
        return new self(sprintf('Type for property "%s" in class "%s" must be object', $propertyName, $class));
    }

    public static function missingContainer(): self
    {
        return new self('Container is not configured');
    }
}