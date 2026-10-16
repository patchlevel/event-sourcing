<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription;

use RuntimeException;

use function sprintf;

final class InvalidPartitionId extends RuntimeException
{
    public function __construct(string $id, string $partitionKey)
    {
        parent::__construct(sprintf(
            'The id "%s" does not belong to a partition with the key "%s", expected "{subscriberId}%s%s".',
            $id,
            $partitionKey,
            Subscription::PARTITION_SEPARATOR,
            $partitionKey,
        ));
    }
}
