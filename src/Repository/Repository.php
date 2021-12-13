<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;

interface Repository
{
    public function load(string $id): AggregateRoot;

    public function has(string $id): bool;

    public function save(AggregateRoot $aggregate): void;
}
