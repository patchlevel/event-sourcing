<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver;

use Patchlevel\EventSourcing\Message\Message;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscriberMetadata;
use Patchlevel\EventSourcing\Subscription\Subscription;

final class ArgumentResolverContext
{
    public function __construct(
        public readonly Message $message,
        public readonly Subscription $subscription,
        public readonly SubscriberMetadata $subscriber,
    ) {
    }
}
