<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\ProjectorStore;

use Patchlevel\EventSourcing\Projection\ProjectorId;

interface ProjectorStore
{
    public function getProjectorState(ProjectorId $projectorId): ProjectorState;

    public function getStateFromAllProjectors(): ProjectorStateCollection;

    public function saveProjectorState(ProjectorState ...$projectorStates): void;

    public function removeProjectorState(ProjectorId $projectorId): void;
}
