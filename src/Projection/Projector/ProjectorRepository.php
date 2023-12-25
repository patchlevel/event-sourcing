<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projector;

interface ProjectorRepository
{
    /** @return list<object> */
    public function projectors(): array;
}
