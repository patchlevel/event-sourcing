<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store\Criteria;

final class FromIndexCriterion
{
    public function __construct(
        public readonly int $fromIndex,
    ) {
    }
}
