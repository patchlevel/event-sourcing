<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store\Criteria;

final class ToIndexCriterion
{
    public function __construct(
        public readonly int $toIndex,
    ) {
    }
}
