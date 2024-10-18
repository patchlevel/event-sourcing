<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Source;

use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\Stream;

final class StoreSource implements Source
{
    public function __construct(
        private readonly Store $store,
    ) {
    }

    public function load(): Stream
    {
        return $this->store->load();
    }
}