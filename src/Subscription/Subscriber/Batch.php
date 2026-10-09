<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber;

use Patchlevel\EventSourcing\Subscription\Subscription;

/** @internal */
final class Batch
{
    public int $count = 0;

    /** @param MetadataSubscriberAccessor<object> $accessor */
    public function __construct(
        public readonly Subscription $subscription,
        public readonly MetadataSubscriberAccessor $accessor,
        public readonly object $state,
    ) {
    }
}
