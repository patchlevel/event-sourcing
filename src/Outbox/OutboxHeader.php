<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Outbox;

use Patchlevel\EventSourcing\Attribute\HeaderIdentifier;
use Patchlevel\EventSourcing\EventBus\Header;

/** @psalm-immutable */
#[HeaderIdentifier('outbox')]
final class OutboxHeader implements Header
{
    public function __construct(
        public readonly int $id,
    ) {
    }
}
