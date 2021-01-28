<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Container;

use Patchlevel\EventSourcing\Repository\Repository;
use Patchlevel\EventSourcing\Store\Store;

class Container
{
    private Store $store;

    public function __construct(Store $store, )
    {
        $this->store = $store;
    }

    public function store(): Store
    {
        return $this->store;
    }

    public function repository(string $aggregateClass): Repository
    {

    }
}
