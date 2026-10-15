<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Subscriber;

/** The partitioner itself is not part of the metadata, it is created from the attribute when it is needed. */
final class PartitionMetadata
{
    /**
     * @param positive-int      $maxConsecutiveFailures
     * @param positive-int|null $removeIdleAfter        seconds
     */
    public function __construct(
        public readonly int $maxConsecutiveFailures = 5,
        public readonly int|null $removeIdleAfter = null,
    ) {
    }
}
