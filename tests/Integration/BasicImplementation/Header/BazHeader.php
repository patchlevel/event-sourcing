<?php

declare(strict_types=1);

namespace Integration\BasicImplementation\Header;

use Patchlevel\EventSourcing\Attribute\HeaderIdentifier;
use Patchlevel\EventSourcing\EventBus\Header;

/** @psalm-immutable */
#[HeaderIdentifier('baz')]
final class BazHeader implements Header
{
    public function __construct(
        public readonly string $data,
    ) {
    }
}
