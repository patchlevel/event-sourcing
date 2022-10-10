<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\ProjectorStore;

use Patchlevel\EventSourcing\Projection\ProjectorId;

use function array_key_exists;
use function array_values;

final class InMemoryStore implements ProjectorStore
{
    /** @var array<string, ProjectorState> */
    private array $store = [];

    public function getProjectorState(ProjectorId $projectorId): ProjectorState
    {
        if (array_key_exists($projectorId->toString(), $this->store)) {
            return $this->store[$projectorId->toString()];
        }

        throw new ProjectorStateNotFound($projectorId);
    }

    public function getStateFromAllProjectors(): ProjectorStateCollection
    {
        return new ProjectorStateCollection(array_values($this->store));
    }

    public function saveProjectorState(ProjectorState ...$projectorStates): void
    {
        foreach ($projectorStates as $state) {
            $this->store[$state->id()->toString()] = $state;
        }
    }

    public function removeProjectorState(ProjectorId $projectorId): void
    {
        unset($this->store[$projectorId->toString()]);
    }
}
