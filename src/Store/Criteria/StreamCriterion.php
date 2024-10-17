<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store\Criteria;

use Patchlevel\EventSourcing\Store\InvalidStreamName;

use function preg_match;

final class StreamCriterion
{
    public function __construct(
        public readonly string $streamName,
    ) {
        if (!preg_match('/^[^*]*\*?$/', $this->streamName)) {
            throw new InvalidStreamName($this->streamName);
        }
    }

    public static function startWith(string $streamName): self
    {
        return new self($streamName . '*');
    }
}
