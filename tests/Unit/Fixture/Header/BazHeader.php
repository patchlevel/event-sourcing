<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Fixture\Header;

use Patchlevel\EventSourcing\Attribute\Header;

/** @psalm-immutable */
#[Header('baz')]
final class BazHeader
{
    public function __construct(
        public readonly string $data,
    ) {
    }
}
