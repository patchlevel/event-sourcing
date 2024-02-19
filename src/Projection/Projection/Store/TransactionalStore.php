<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projection\Store;

use Closure;

interface TransactionalStore
{
    public function transactional(Closure $closure): void;
}
