<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Target;

use Patchlevel\EventSourcing\Pipeline\EventBucket;
use Patchlevel\EventSourcing\Projection\Projection;
use Patchlevel\EventSourcing\Projection\ProjectionRepository;

class ProjectionTarget implements Target
{
    private ProjectionRepository $projectionRepository;

    public function __construct(Projection $projection)
    {
        $this->projectionRepository = new ProjectionRepository([$projection]);
    }

    public function save(EventBucket $bucket): void
    {
        $this->projectionRepository->handle($bucket->event());
    }
}
