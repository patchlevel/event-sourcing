<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store\Header;

/** @immutable */
final class EventIdHeader
{
    public function __construct(
        public readonly string $eventId,
    ) {
    }
}
