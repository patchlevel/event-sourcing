<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorState;

final class ProjectorInformation
{
    public function __construct(
        public readonly ProjectorState $projectorState,
        public readonly ?Projector $projector = null
    ) {
    }
}
