<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\EventBus\Listener;

final class ProjectionListener implements Listener
{
    private ProjectionRepository $repository;

    public function __construct(ProjectionRepository $repository)
    {
        $this->repository = $repository;
    }

    public function __invoke(AggregateChanged $event): void
    {
        $this->repository->handle($event);
    }
}
