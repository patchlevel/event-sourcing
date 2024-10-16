<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

interface StreamStore extends Store
{
    /** @return list<string> */
    public function streams(): array;

    public function remove(string $streamName): void;
}
