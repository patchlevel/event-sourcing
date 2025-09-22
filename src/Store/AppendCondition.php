<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

/** @experimental */
final class AppendCondition
{
    public function __construct(
        public readonly Query $query = new Query(),
        public readonly int|null $highestSequenceNumber = null,
    ) {
    }
}
