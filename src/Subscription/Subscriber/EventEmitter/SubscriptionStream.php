<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber\EventEmitter;

final class SubscriptionStream
{
    public const PREFIX = 'subscription_';

    public static function name(string $subscriptionId): string
    {
        return self::PREFIX . $subscriptionId;
    }
}
