<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Outbox;

use Patchlevel\EventSourcing\EventBus\Header;

/** @psalm-immutable */
#[\Patchlevel\EventSourcing\Attribute\Header('outbox')]
final class OutboxHeader implements Header
{
    public function __construct(
        public readonly int $id,
    ) {
    }
}
