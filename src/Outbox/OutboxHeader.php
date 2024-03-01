<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Outbox;

use Patchlevel\EventSourcing\Attribute\HeaderIdentifier;

/** @psalm-immutable */
#[HeaderIdentifier('outbox')]
final class OutboxHeader
{
    public function __construct(
        public readonly int $id,
    ) {
    }
}
