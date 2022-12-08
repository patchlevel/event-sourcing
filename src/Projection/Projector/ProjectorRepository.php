<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projector;

interface ProjectorRepository
{
    /**
     * @return list<Projector>
     */
    public function projectors(): array;
}
