<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store\Criteria;

final class AggregateIdCriterion
{
    public function __construct(
        public readonly string $aggregateId,
    ) {
    }
}
