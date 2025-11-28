<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store\Header;

/** @immutable */
final class IndexHeader
{
    /** @param positive-int $index */
    public function __construct(
        public readonly int $index,
    ) {
    }
}
