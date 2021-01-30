<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Generator;
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

interface StreamableStore extends Store
{
    /**
     * @return Generator<AggregateChanged>
     */
    public function all(): Generator;

    public function count(): int;
}
