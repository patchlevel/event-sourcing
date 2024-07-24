<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store\Criteria;

final class StreamCriterion
{
    public function __construct(
        public readonly string $streamName,
    ) {
    }
}
