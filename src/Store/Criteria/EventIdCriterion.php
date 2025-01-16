<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store\Criteria;

final class EventIdCriterion
{
    public function __construct(
        public readonly string $eventId,
    ) {
    }
}
