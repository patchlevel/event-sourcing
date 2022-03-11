<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Target;

use Patchlevel\EventSourcing\Pipeline\EventBucket;
use Patchlevel\EventSourcing\Projection\ProjectionHandler;

final class ProjectionHandlerTarget implements Target
{
    private ProjectionHandler $projectionHandler;

    public function __construct(ProjectionHandler $projectionHandler)
    {
        $this->projectionHandler = $projectionHandler;
    }

    public function save(EventBucket $bucket): void
    {
        $this->projectionHandler->handle($bucket->event());
    }
}
