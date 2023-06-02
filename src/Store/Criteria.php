<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

final class Criteria
{
    public function __construct(
        public readonly ?string $aggregateClass = null,
        public readonly ?string $aggregateId = null,
        public readonly ?int $limit = null,
        public readonly ?int $fromIndex = null,
        public readonly ?int $fromPlayhead = null,
        public readonly ?bool $archived = null,
    ) {
    }
}
