<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use DateTimeImmutable;

/** @psalm-immutable */
final class StreamHeader
{
    /** @param positive-int|null $playhead */
    public function __construct(
        public readonly string $streamName,
        public readonly int|null $playhead = null,
        public readonly DateTimeImmutable|null $recordedOn = null,
    ) {
    }
}
