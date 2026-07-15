<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber;

use RuntimeException;

use function sprintf;

final class BatchNotFound extends RuntimeException
{
    public function __construct(string $subscriptionId)
    {
        parent::__construct(sprintf('No batch found for subscription "%s".', $subscriptionId));
    }
}
