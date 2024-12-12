<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store\Criteria;

final class EventsCriterion
{
    public function __construct(
        /** @var list<string> */
        public readonly array $events,
    ) {
    }
}
