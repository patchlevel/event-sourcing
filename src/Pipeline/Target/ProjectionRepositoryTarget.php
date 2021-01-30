<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Target;

use Patchlevel\EventSourcing\Pipeline\EventBucket;
use Patchlevel\EventSourcing\Projection\ProjectionRepository;

class ProjectionRepositoryTarget implements Target
{
    private ProjectionRepository $projectionRepository;

    public function __construct(ProjectionRepository $projectionRepository)
    {
        $this->projectionRepository = $projectionRepository;
    }

    public function save(EventBucket $bucket): void
    {
        $this->projectionRepository->handle($bucket->event());
    }
}
