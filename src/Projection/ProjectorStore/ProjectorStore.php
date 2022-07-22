<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\ProjectorStore;

use Patchlevel\EventSourcing\Projection\Projector\ProjectorId;

interface ProjectorStore
{
    public function get(ProjectorId $projectorId): ProjectorData;

    /** @return list<ProjectorData> */
    public function all(): array;

    public function save(ProjectorData ...$positions): void;
}
