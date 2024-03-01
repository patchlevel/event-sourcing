<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Patchlevel\EventSourcing\Attribute\HeaderIdentifier;

/** @psalm-immutable */
#[HeaderIdentifier('archived')]
final class ArchivedHeader
{
    public function __construct(
        public readonly bool $archived,
    ) {
    }
}
