<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\Projection\Projector\Projector;
use Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorData;

final class ProjectorInformation
{
    public function __construct(
        public readonly ProjectorData $projectorData,
        public readonly ?Projector $projector = null
    ) {
    }
}
