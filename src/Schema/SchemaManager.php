<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Schema;

use Patchlevel\EventSourcing\Store\Store;

interface SchemaManager
{
    public function create(Store $store): void;

    public function update(Store $store): void;

    public function drop(Store $store): void;
}
