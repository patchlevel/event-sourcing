<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Source;

use Generator;
use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Store\PipelineStore;

final class StoreSource implements Source
{
    private PipelineStore $store;
    private int $fromIndex;

    public function __construct(PipelineStore $store, int $fromIndex = 0)
    {
        $this->store = $store;
        $this->fromIndex = $fromIndex;
    }

    /**
     * @return Generator<Message>
     */
    public function load(): Generator
    {
        return $this->store->stream($this->fromIndex);
    }

    public function count(): int
    {
        return $this->store->count($this->fromIndex);
    }
}
