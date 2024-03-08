<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber;

interface SubscriberAccessorRepository
{
    /** @return iterable<SubscriberAccessor> */
    public function all(): iterable;

    public function get(string $id): SubscriberAccessor|null;
}
