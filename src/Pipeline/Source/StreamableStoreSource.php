<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Source;

use Generator;
use Patchlevel\EventSourcing\Store\StreamableStore;

class StreamableStoreSource implements Source
{
    private StreamableStore $store;

    public function __construct(StreamableStore $store)
    {
        $this->store = $store;
    }

    public function load(): Generator
    {
        return $this->store->all();
    }

    public function count(): int
    {
        return $this->store->count();
    }
}
