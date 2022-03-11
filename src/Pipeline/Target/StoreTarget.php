<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Target;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Store\Store;

final class StoreTarget implements Target
{
    private Store $store;

    public function __construct(Store $store)
    {
        $this->store = $store;
    }

    public function save(Message $message): void
    {
        $this->store->save($message);
    }
}
