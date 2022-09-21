<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\EventBus\Listener;
use Patchlevel\EventSourcing\EventBus\Message;

/**
 * @deprecated use SyncProjectorListener
 */
final class ProjectionListener implements Listener
{
    private ProjectionHandler $projectionHandler;

    public function __construct(ProjectionHandler $projectionHandler)
    {
        $this->projectionHandler = $projectionHandler;
    }

    public function __invoke(Message $message): void
    {
        $this->projectionHandler->handle($message);
    }
}
