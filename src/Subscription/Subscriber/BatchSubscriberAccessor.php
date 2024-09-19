<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber;

use Closure;

interface BatchSubscriberAccessor
{
    public function batch(): bool;

    public function beginBatchMethod(): Closure|null;

    public function commitBatchMethod(): Closure|null;

    public function rollbackBatchMethod(): Closure|null;
}
