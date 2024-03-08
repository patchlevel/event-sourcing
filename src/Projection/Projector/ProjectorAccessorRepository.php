<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projector;

interface ProjectorAccessorRepository
{
    /** @return iterable<ProjectorAccessor> */
    public function all(): iterable;

    public function get(string $id): ProjectorAccessor|null;
}
