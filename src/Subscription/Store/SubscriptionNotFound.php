<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Store;

use RuntimeException;

use function sprintf;

final class SubscriptionNotFound extends RuntimeException
{
    public function __construct(string $subscriptionId)
    {
        parent::__construct(sprintf('Subscription with the id "%s" not found.', $subscriptionId));
    }
}
