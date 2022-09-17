<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\ProjectorStore;

use Patchlevel\EventSourcing\Projection\ProjectorId;

interface ProjectorStore
{
    public function getProjectorState(ProjectorId $projectorId): ProjectorState;

    /** @return list<ProjectorState> */
    public function getStateFromAllProjectors(): array;

    public function saveProjectorState(ProjectorState ...$projectorStates): void;

    public function removeProjectorState(ProjectorId $projectorId): void;
}
