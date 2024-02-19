<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projection\Store;

use Closure;

interface LockableProjectionStore extends ProjectionStore
{
    public function inLock(Closure $closure): void;
}
