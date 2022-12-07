<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projector;

use Patchlevel\EventSourcing\Projection\Projection\ProjectionId;

interface ProjectorRepository
{
    public function findByProjectionId(ProjectionId $projectionId): ?StatefulProjector;

    /**
     * @return list<Projector>
     */
    public function projectors(): array;

    /**
     * @return list<StatefulProjector>
     */
    public function statefulProjectors(): array;
}
