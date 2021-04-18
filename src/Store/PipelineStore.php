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
    public function stream(int $fromIndex = 0): Generator;

    public function count(int $fromIndex = 0): int;

    public function saveEventBucket(EventBucket $bucket): void;
}
