<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\ProjectorStore;

use Patchlevel\EventSourcing\Projection\ProjectorId;
use RuntimeException;

use function array_key_exists;
use function array_values;

class InMemory implements ProjectorStore
{
    /** @var array<string, ProjectorState> */
    private array $store = [];

    public function getProjectorState(ProjectorId $projectorId): ProjectorState
    {
        if (array_key_exists($projectorId->toString(), $this->store)) {
            return $this->store[$projectorId->toString()];
        }

        throw new RuntimeException(); // todo
    }

    /** @return list<ProjectorState> */
    public function getStateFromAllProjectors(): array
    {
        return array_values($this->store);
    }

    public function saveProjectorState(ProjectorState ...$data): void
    {
        foreach ($data as $item) {
            $this->store[$item->id()->toString()] = $item;
        }
    }

    public function removeProjectorState(ProjectorId $projectorId): void
    {
        unset($this->store[$projectorId->toString()]);
    }
}
