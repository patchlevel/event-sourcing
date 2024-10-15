<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber;

interface BatchableSubscriber
{
    public function beginBatch(): void;

    public function commitBatch(): void;

    public function rollbackBatch(): void;

    public function forceCommit(): bool;
}
