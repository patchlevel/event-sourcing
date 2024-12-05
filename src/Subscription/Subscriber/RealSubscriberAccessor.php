<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber;

/** @deprecated will be removed, use MetadataSubscriberAccessor directly */
interface RealSubscriberAccessor
{
    public function realSubscriber(): object;
}
