<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Generator;
use Patchlevel\EventSourcing\Pipeline\EventBucket;

interface PipelineStore extends Store
{
    /**
     * @return Generator<EventBucket>
     */
    public function all(): Generator;

    public function count(): int;

    public function save(EventBucket $bucket): void;
}
