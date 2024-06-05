<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store\Criteria;

final class FromIndexWithTransactionIdCriterion
{
    public function __construct(
        public readonly int $fromIndex,
        public readonly int $transactionId,
    ) {
    }
}
