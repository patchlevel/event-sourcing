<?php

declare(strict_types=1);

namespace Integration\BasicImplementation\Header;

use Patchlevel\EventSourcing\EventBus\Header;

/** @psalm-immutable */
#[\Patchlevel\EventSourcing\Attribute\Header('foo')]
final class FooHeader implements Header
{
    public function __construct(
        public readonly string $data,
    ) {
    }
}
