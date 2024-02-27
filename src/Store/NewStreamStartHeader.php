<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Patchlevel\EventSourcing\EventBus\Header;

/** @psalm-immutable */
#[\Patchlevel\EventSourcing\Attribute\Header('newStreamStart')]
final class NewStreamStartHeader implements Header
{
    public function __construct(
        public readonly bool $newStreamStart,
    ) {
    }
}
