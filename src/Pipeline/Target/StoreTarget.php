<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Target;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Store\Store;

final class StoreTarget implements Target
{
    public function __construct(
        private readonly Store $store,
    ) {
    }

    public function save(Message ...$messages): void
    {
        $this->store->save(...$messages);
    }
}
