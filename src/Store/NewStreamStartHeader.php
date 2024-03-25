<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

/** @psalm-immutable */
final class NewStreamStartHeader
{
    public function __construct(
        public readonly bool $newStreamStart,
    ) {
    }
}
