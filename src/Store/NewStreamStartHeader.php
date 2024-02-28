<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Patchlevel\EventSourcing\Attribute\HeaderIdentifier;
use Patchlevel\EventSourcing\EventBus\Header;

/** @psalm-immutable */
#[HeaderIdentifier('newStreamStart')]
final class NewStreamStartHeader implements Header
{
    public function __construct(
        public readonly bool $newStreamStart,
    ) {
    }
}
