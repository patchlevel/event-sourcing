<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projector;

use Patchlevel\EventSourcing\Projection\Projection\ProjectionId;

interface StatefulProjector extends Projector
{
    public function projectionId(): ProjectionId;
}
