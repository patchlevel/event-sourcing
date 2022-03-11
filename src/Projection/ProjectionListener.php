<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\Aggregate\AggregateChanged;
use Patchlevel\EventSourcing\EventBus\Listener;

final class ProjectionListener implements Listener
{
    private ProjectionHandler $projectionHandler;

    public function __construct(ProjectionHandler $projectionHandler)
    {
        $this->projectionHandler = $projectionHandler;
    }

    public function __invoke(AggregateChanged $event): void
    {
        $this->projectionHandler->handle($event);
    }
}
