<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

interface ProjectorRepository
{
    public function findByProjectorId(ProjectorId $projectorId): ?Projector;

    /**
     * @return list<Projector>
     */
    public function projectors(): array;
}
