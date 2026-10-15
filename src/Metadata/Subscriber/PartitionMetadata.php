<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Subscriber;

final class PartitionMetadata
{
    /** @param positive-int $maxConsecutiveFailures */
    public function __construct(
        public readonly string $method,
        public readonly int $maxConsecutiveFailures = 5,
    ) {
    }
}
