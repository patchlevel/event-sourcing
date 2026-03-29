<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\StatefulSubscriber;

use Patchlevel\EventSourcing\Subscription\Subscriber\BatchableSubscriber;
use Patchlevel\Hydrator\Attribute\Ignore;

abstract class StatefulSubscriber implements BatchableSubscriber
{
    public function __construct(
        #[Ignore]
        private readonly StatefulSubscriberStore $store,
    ) {
        $this->store->load($this);
    }

    public function beginBatch(): void
    {
        // do nothing
    }

    public function commitBatch(): void
    {
        $this->store->store($this);
    }

    public function rollbackBatch(): void
    {
        $this->store->load($this);
    }

    public function forceCommit(): bool
    {
        return false;
    }
}
