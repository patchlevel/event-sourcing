<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Source;

use Generator;
use Patchlevel\EventSourcing\Pipeline\EventBucket;

interface Source
{
    /**
     * @return Generator<EventBucket>
     */
    public function load(): Generator;

    public function count(): int;
}
