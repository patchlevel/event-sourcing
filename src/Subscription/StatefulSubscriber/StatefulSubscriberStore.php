<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\StatefulSubscriber;

interface StatefulSubscriberStore
{
    public function store(StatefulSubscriber $subscriber): void;

    public function load(StatefulSubscriber $subscriber): void;
}
