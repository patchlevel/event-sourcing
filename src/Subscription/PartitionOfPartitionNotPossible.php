<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription;

use RuntimeException;

use function sprintf;

final class PartitionOfPartitionNotPossible extends RuntimeException
{
    public function __construct(string $subscriptionId)
    {
        parent::__construct(sprintf('The subscription "%s" is already a partition.', $subscriptionId));
    }
}
