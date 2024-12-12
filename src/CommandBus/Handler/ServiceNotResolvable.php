<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus\Handler;

use RuntimeException;

use function sprintf;

final class ServiceNotResolvable extends RuntimeException
{
    /** @param class-string $class */
    public static function missingType(string $class, string $propertyName): self
    {
        return new self(sprintf('Missing type for property "%s" in class "%s"', $propertyName, $class));
    }

    /** @param class-string $class */
    public static function typeNotObject(string $class, string $propertyName): self
    {
        return new self(sprintf('Type for property "%s" in class "%s" must be object', $propertyName, $class));
    }

    public static function missingContainer(): self
    {
        return new self('Container is not configured');
    }
}
