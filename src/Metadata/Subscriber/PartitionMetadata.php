<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Subscriber;

use Patchlevel\EventSourcing\Subscription\Partitioner\Partitioner;

final class PartitionMetadata
{
    /**
     * @param positive-int      $maxConsecutiveFailures
     * @param positive-int|null $removeIdleAfter        seconds
     */
    public function __construct(
        public readonly Partitioner $partitioner,
        public readonly int $maxConsecutiveFailures = 5,
        public readonly int|null $removeIdleAfter = null,
    ) {
    }
}
