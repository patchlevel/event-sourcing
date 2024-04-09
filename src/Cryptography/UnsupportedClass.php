<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Cryptography;

use RuntimeException;

use function sprintf;

final class UnsupportedClass extends RuntimeException
{
    public static function fromClass(string $class): self
    {
        return new self(sprintf('unsupported class %s', $class));
    }
}
