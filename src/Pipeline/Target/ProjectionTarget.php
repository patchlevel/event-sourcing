<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Target;

use Patchlevel\EventSourcing\Pipeline\EventBucket;
use Patchlevel\EventSourcing\Projection\Projection;
use Patchlevel\EventSourcing\Projection\ProjectionHandler;

final class ProjectionTarget implements Target
{
    private ProjectionHandler $projectionHandler;

    /** @var non-empty-array<class-string<Projection>>|null */
    private ?array $onlyProjections;

    /**
     * @param non-empty-array<class-string<Projection>>|null $onlyProjections
     */
    public function __construct(ProjectionHandler $projectionHandler, ?array $onlyProjections = null)
    {
        $this->projectionHandler = $projectionHandler;
        $this->onlyProjections = $onlyProjections;
    }

    public function save(EventBucket $bucket): void
    {
        $this->projectionHandler->handle(
            $bucket->event(),
            $this->onlyProjections
        );
    }
}
