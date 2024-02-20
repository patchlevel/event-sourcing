<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projection\Store;

use Patchlevel\EventSourcing\Projection\Projection\Projection;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionAlreadyExists;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionCriteria;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionNotFound;

interface ProjectionStore
{
    /** @throws ProjectionNotFound */
    public function get(string $projectionId): Projection;

    /** @return list<Projection> */
    public function find(ProjectionCriteria|null $criteria = null): array;

    /** @throws ProjectionAlreadyExists */
    public function add(Projection $projection): void;

    /** @throws ProjectionNotFound */
    public function update(Projection $projection): void;

    /** @throws ProjectionNotFound */
    public function remove(Projection $projection): void;
}
