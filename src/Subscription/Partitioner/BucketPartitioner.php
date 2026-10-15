<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Partitioner;

use InvalidArgumentException;
use Patchlevel\EventSourcing\Message\Message;

use function crc32;
use function sprintf;

/**
 * Spreads the keys of another partitioner over a fixed number of partitions, e.g.
 * `new BucketPartitioner(new StreamPartitioner(), 16)`. Everything with the same key stays in the same bucket.
 */
final class BucketPartitioner implements Partitioner
{
    /** @param positive-int $buckets */
    public function __construct(
        private readonly Partitioner $partitioner,
        private readonly int $buckets,
    ) {
        // @phpstan-ignore smaller.alwaysFalse (the attribute is not checked by static analysis)
        if ($buckets < 1) {
            throw new InvalidArgumentException(sprintf('The number of buckets must be positive, %d given.', $buckets));
        }
    }

    public function __invoke(Message $message): string
    {
        return (string)(crc32(($this->partitioner)($message)) % $this->buckets);
    }
}
