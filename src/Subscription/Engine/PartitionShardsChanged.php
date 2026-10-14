<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use RuntimeException;

use function sprintf;

final class PartitionShardsChanged extends RuntimeException
{
    public function __construct(string $subscriberId)
    {
        parent::__construct(
            sprintf(
                'The number of shards of the partitioned subscriber "%s" has changed. The existing partitions have to be rebuilt (teardown and setup).',
                $subscriberId,
            ),
        );
    }
}
