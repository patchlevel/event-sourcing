<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

/** @psalm-immutable */
final class ArchivedHeader
{
    public function __construct(
        public readonly bool $archived,
    ) {
    }
}
