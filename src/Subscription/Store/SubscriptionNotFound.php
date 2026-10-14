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

    public static function forPartition(string $subscriberId, string $partitionKey): self
    {
        $exception = new self($subscriberId);
        $exception->message = sprintf(
            'Partition "%s" of the subscription with the id "%s" not found.',
            $partitionKey,
            $subscriberId,
        );

        return $exception;
    }
}
