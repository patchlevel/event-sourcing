<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Patchlevel\EventSourcing\Attribute\Header;

/** @psalm-immutable */
#[Header('archived')]
final class ArchivedHeader
{
    public function __construct(
        public readonly bool $archived,
    ) {
    }
}
