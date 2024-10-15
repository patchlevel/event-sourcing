<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber;

interface RealSubscriberAccessor
{
    public function realSubscriber(): object;
}
