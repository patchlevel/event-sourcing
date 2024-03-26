<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Subscriber;

use Patchlevel\EventSourcing\Metadata\MetadataException;

use function sprintf;

final class ArgumentTypeNotSupported extends MetadataException
{
    public static function missingType(string $class, string $method, string $argumentName): self
    {
        return new self(
            sprintf(
                'Argument type for method "%s" in class "%s" is not supported. Argument "%s" must have a type.',
                $method,
                $class,
                $argumentName,
            ),
        );
    }

    public static function onlyNamedTypeSupported(string $class, string $method, string $argumentName): self
    {
        return new self(
            sprintf(
                'Argument type for method "%s" in class "%s" is not supported. Argument "%s" must not have a union or intersection type.',
                $method,
                $class,
                $argumentName,
            ),
        );
    }
}
