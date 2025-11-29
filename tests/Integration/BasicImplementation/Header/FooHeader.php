<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Integration\BasicImplementation\Header;

use Patchlevel\EventSourcing\Attribute\Header;

/** @immutable */
#[Header('foo')]
final class FooHeader
{
    public function __construct(
        public readonly string $data,
    ) {
    }
}
