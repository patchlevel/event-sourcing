<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\RetryStrategy;

use Patchlevel\EventSourcing\Projection\Projection\Projection;

interface RetryStrategy
{
    public function shouldRetry(Projection $projection): bool;
}
