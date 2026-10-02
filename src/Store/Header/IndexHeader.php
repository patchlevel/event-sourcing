<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store\Header;

use Patchlevel\EventSourcing\Attribute\Header;

/** @immutable */
#[Header('index')]
final class IndexHeader
{
    /** @param positive-int $index */
    public function __construct(
        public readonly int $index,
    ) {
    }
}
