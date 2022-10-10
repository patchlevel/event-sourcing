<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Tests\Unit\Projection;

use Patchlevel\EventSourcing\Projection\ProjectorId;
use Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorState;
use Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorStateCollection;
use Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorStateNotFound;
use Patchlevel\EventSourcing\Projection\ProjectorStore\ProjectorStore;

use function array_key_exists;
use function array_values;

final class DummyStore implements ProjectorStore
{
    /** @var array<string, ProjectorState> */
    private array $store = [];
    /** @var list<ProjectorState> */
    public array $savedStates = [];
    /** @var list<ProjectorId> */
    public array $removedIds = [];

    /**
     * @param list<ProjectorState> $store
     */
    public function __construct(array $store = [])
    {
        foreach ($store as $state) {
            $this->store[$state->id()->toString()] = $state;
        }
    }

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
            $this->savedStates[] = clone $state;
        }
    }

    public function removeProjectorState(ProjectorId $projectorId): void
    {
        $this->removedIds[] = $projectorId;
        unset($this->store[$projectorId->toString()]);
    }
}
