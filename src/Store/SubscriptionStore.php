<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

interface SubscriptionStore
{
    public function supportSubscription(): bool;

    public function setupSubscription(): void;

    public function wait(int $timeoutMilliseconds): void;

    // public function teardownSubscription(): void;
}
