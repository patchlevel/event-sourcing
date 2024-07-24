<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store\Criteria;

final class StreamCriterion
{
    public function __construct(
        public readonly string $streamName,
    ) {
    }

    public static function startWith(string $streamName): self
    {
        return new self($streamName . '*');
    }
}
