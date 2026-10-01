<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Patchlevel\EventSourcing\Subscription\Subscription;

interface MessageFilter
{
    /**
     * Returns the event names which should be loaded for the subscriptions.
     * An empty list means that all events are loaded.
     *
     * @param list<Subscription> $subscriptions
     *
     * @return list<string>
     */
    public function events(array $subscriptions): array;
}
