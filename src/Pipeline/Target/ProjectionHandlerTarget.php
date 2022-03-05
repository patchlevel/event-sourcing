<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Target;

use Patchlevel\EventSourcing\Pipeline\EventBucket;
use Patchlevel\EventSourcing\Projection\Projection;
use Patchlevel\EventSourcing\Projection\ProjectionHandler;

final class ProjectionHandlerTarget implements Target
{
    private ProjectionHandler $projectionRepository;

    /** @var non-empty-array<Projection>|null */
    private ?array $onlyProjections;

    /**
     * @param non-empty-array<Projection>|null $onlyProjections
     */
    public function __construct(ProjectionHandler $projectionRepository, ?array $onlyProjections = null)
    {
        $this->projectionRepository = $projectionRepository;
        $this->onlyProjections = $onlyProjections;
    }

    public function save(EventBucket $bucket): void
    {
        $this->projectionRepository->handle(
            $bucket->event(),
            $this->onlyProjections
        );
    }
}
