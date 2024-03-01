<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Patchlevel\EventSourcing\Attribute\Header;

/** @psalm-immutable */
#[Header('newStreamStart')]
final class NewStreamStartHeader
{
    public function __construct(
        public readonly bool $newStreamStart,
    ) {
    }
}
