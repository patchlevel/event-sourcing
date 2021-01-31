<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Source;

use Generator;
use Patchlevel\EventSourcing\Pipeline\EventBucket;
use Patchlevel\EventSourcing\Store\PipelineStore;

class StoreSource implements Source
{
    private PipelineStore $store;

    public function __construct(PipelineStore $store)
    {
        $this->store = $store;
    }

    /**
     * @return Generator<EventBucket>
     */
    public function load(): Generator
    {
        return $this->store->all();
    }

    public function count(): int
    {
        return $this->store->count();
    }
}
