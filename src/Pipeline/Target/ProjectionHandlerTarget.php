<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Target;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\ProjectionHandler;

final class ProjectionHandlerTarget implements Target
{
    private ProjectionHandler $projectionHandler;

    public function __construct(ProjectionHandler $projectionHandler)
    {
        $this->projectionHandler = $projectionHandler;
    }

    public function save(Message $message): void
    {
        $this->projectionHandler->handle($message);
    }
}
