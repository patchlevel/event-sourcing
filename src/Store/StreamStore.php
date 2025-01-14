<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use Patchlevel\EventSourcing\Store\Criteria\Criteria;

interface StreamStore extends Store
{
    /** @return list<string> */
    public function streams(): array;

    public function remove(Criteria|null $criteria = null): void;

    public function archive(Criteria|null $criteria = null): void;
}
