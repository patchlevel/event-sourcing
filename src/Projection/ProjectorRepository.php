<?php

namespace Patchlevel\EventSourcing\Projection;

interface ProjectorRepository
{
    public function findByProjectorId(ProjectorId $projectorId): ?Projector;

    public function findByProjectorName(string $name): ?Projector;

    /**
     * @return list<Projector>
     */
    public function projectors(): array;
}