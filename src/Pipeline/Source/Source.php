<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Source;

use Generator;
use Patchlevel\EventSourcing\Aggregate\AggregateChanged;

interface Source
{
    /**
     * @return Generator<AggregateChanged>
     */
    public function load(): Generator;

    public function count(): int;
}
