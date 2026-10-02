<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store\Header;

use Patchlevel\EventSourcing\Attribute\Header;

/** @immutable */
#[Header('eventId')]
final class EventIdHeader
{
    public function __construct(
        public readonly string $eventId,
    ) {
    }
}
