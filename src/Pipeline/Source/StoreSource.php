<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Source;

use Generator;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Store\StreamableStore;

final class StoreSource implements Source
{
    public function __construct(private StreamableStore $store, private int $fromIndex = 0)
    {
    }

    /** @return Generator<Message> */
    public function load(): Generator
    {
        return $this->store->stream($this->fromIndex);
    }

    public function count(): int
    {
        return $this->store->count($this->fromIndex);
    }
}
