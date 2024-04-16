<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store\Criteria;

final class ArchivedCriterion
{
    public function __construct(
        public readonly bool $archived,
    ) {
    }
}
