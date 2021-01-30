<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Target;

use Patchlevel\EventSourcing\Pipeline\EventBucket;
use Patchlevel\EventSourcing\Store\PipelineStore;

class StoreTarget implements Target
{
    private PipelineStore $store;

    public function __construct(PipelineStore $store)
    {
        $this->store = $store;
    }

    public function save(EventBucket $bucket): void
    {
        $this->store->save($bucket);
    }
}
