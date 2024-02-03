<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

final class Criteria
{
    public function __construct(
        public readonly string|null $aggregateName = null,
        public readonly string|null $aggregateId = null,
        public readonly int|null $fromIndex = null,
        public readonly int|null $fromPlayhead = null,
        public readonly bool|null $archived = null,
    ) {
    }
}
