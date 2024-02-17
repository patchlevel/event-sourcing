<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projection\Store;

use Patchlevel\EventSourcing\Projection\Projection\Projection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionCollection;

interface ProjectionStore
{
    public function get(string $projectionId): Projection;

    public function all(): ProjectionCollection;

    public function save(Projection ...$projections): void;

    public function remove(string ...$projectionIds): void;
}
