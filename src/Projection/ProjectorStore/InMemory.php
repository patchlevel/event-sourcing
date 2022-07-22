<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\ProjectorStore;

use Patchlevel\EventSourcing\Projection\Projector\ProjectorId;
use RuntimeException;

use function array_key_exists;
use function array_values;

class InMemory implements ProjectorStore
{
    /** @var array<string, ProjectorData> */
    private array $store = [];

    public function get(ProjectorId $projectorId): ProjectorData
    {
        if (array_key_exists($projectorId->toString(), $this->store)) {
            return $this->store[$projectorId->toString()];
        }

        throw new RuntimeException(); // todo
    }

    /** @return list<ProjectorData> */
    public function all(): array
    {
        return array_values($this->store);
    }

    public function save(ProjectorData ...$data): void
    {
        foreach ($data as $item) {
            $this->store[$item->id()->toString()] = $item;
        }
    }
}
