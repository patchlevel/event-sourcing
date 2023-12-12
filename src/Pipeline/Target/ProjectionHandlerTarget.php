<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Target;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Projection\ProjectionHandler;

/** @deprecated use ProjectorRepositoryTarget instead */
final class ProjectionHandlerTarget implements Target
{
    public function __construct(private ProjectionHandler $projectionHandler)
    {
    }

    public function save(Message $message): void
    {
        $this->projectionHandler->handle($message);
    }
}
