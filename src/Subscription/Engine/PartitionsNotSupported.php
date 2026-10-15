<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Patchlevel\EventSourcing\Subscription\Store\PartitionInboxStore;
use RuntimeException;

use function sprintf;

final class PartitionsNotSupported extends RuntimeException
{
    /** @param class-string $store */
    public function __construct(string $store)
    {
        parent::__construct(sprintf(
            'The subscription store "%s" does not support partitions, it has to implement "%s".',
            $store,
            PartitionInboxStore::class,
        ));
    }
}
