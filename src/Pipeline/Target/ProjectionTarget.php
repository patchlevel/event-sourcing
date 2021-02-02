<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Target;

use Patchlevel\EventSourcing\Pipeline\EventBucket;
use Patchlevel\EventSourcing\Projection\DefaultProjectionRepository;
use Patchlevel\EventSourcing\Projection\Projection;

class ProjectionTarget implements Target
{
    private DefaultProjectionRepository $projectionRepository;

    public function __construct(Projection $projection)
    {
        $this->projectionRepository = new DefaultProjectionRepository([$projection]);
    }

    public function save(EventBucket $bucket): void
    {
        $this->projectionRepository->handle($bucket->event());
    }
}
