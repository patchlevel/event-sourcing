<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;
use Patchlevel\EventSourcing\Subscription\Partitioner\Partitioner;

/**
 * Splits a subscriber into partitions, the partitioner returns the partition key of every message,
 * e.g. `#[Partition(new StreamPartitioner())]`.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class Partition
{
    public function __construct(
        public readonly Partitioner $partitioner,
        /**
         * After that many partitions of the subscriber failed in a row, without any success in between, the failure is
         * treated as systemic (e.g. the database is down): the main subscription goes into the error status instead of
         * every single partition, and the partitions wait until it is retried.
         *
         * @var positive-int
         */
        public readonly int $maxConsecutiveFailures = 5,
        /**
         * Partitions are kept forever by default. With a number of seconds, a partition that processed its inbox and
         * did nothing for that long is removed. It is created again with the next message of its key.
         *
         * @var positive-int|null
         */
        public readonly int|null $removeIdleAfter = null,
    ) {
    }
}
