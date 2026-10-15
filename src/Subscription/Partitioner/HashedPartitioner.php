<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Partitioner;

use Patchlevel\EventSourcing\Message\Message;

use function hash;

/**
 * Hashes the key of another partitioner, e.g. `new HashedPartitioner(new TagPartitioner('email:'))`.
 * The key always has the same short length, and keys that only differ in case or contain any characters are fine.
 */
final class HashedPartitioner implements Partitioner
{
    public function __construct(
        private readonly Partitioner $partitioner,
        private readonly string $algorithm = 'xxh128',
    ) {
    }

    public function __invoke(Message $message): string
    {
        return hash($this->algorithm, ($this->partitioner)($message));
    }
}
