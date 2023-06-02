<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;

final class Criteria
{
    public function __construct(
        /** @var class-string<AggregateRoot>|null */
        public readonly string|null $aggregateClass = null,
        public readonly string|null $aggregateId = null,
        public readonly int|null $limit = null,
        public readonly int|null $fromIndex = null,
        public readonly int|null $fromPlayhead = null,
        public readonly bool|null $archived = null,
    ) {
    }
}
