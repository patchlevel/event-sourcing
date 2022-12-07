<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projection\Store;

use Patchlevel\EventSourcing\Projection\Projection\Projection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionCollection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionId;

interface ProjectionStore
{
    public function get(ProjectionId $projectionId): Projection;

    public function all(): ProjectionCollection;

    public function save(Projection ...$projections): void;

    public function remove(ProjectionId ...$projectionIds): void;
}
