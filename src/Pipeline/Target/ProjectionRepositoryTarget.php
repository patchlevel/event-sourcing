<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Target;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\Projection\ProjectionRepository;

class ProjectionRepositoryTarget implements Target
{
    private ProjectionRepository $projectionRepository;

    public function __construct(ProjectionRepository $projectionRepository)
    {
        $this->projectionRepository = $projectionRepository;
    }

    public function save(AggregateChanged $event): void
    {
        $this->projectionRepository->handle($event);
    }
}
