<?php

declare(strict_types=1);

namespace Integration\BasicImplementation\Header;

use Patchlevel\EventSourcing\Attribute\HeaderIdentifier;

/** @psalm-immutable */
#[HeaderIdentifier('foo')]
final class FooHeader
{
    public function __construct(
        public readonly string $data,
    ) {
    }
}
