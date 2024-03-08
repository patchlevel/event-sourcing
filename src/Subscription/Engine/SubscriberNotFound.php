<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use RuntimeException;

use function sprintf;

final class SubscriberNotFound extends RuntimeException
{
    public static function forSubscriptionId(string $subscriptionId): self
    {
        return new self(
            sprintf(
                'Subscriber with the subscription id "%s" not found.',
                $subscriptionId,
            ),
        );
    }
}
