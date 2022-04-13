<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Target;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Metadata\Projection\ProjectionMetadataFactory;
use Patchlevel\EventSourcing\Projection\DefaultProjectionHandler;
use Patchlevel\EventSourcing\Projection\Projection;

final class ProjectionTarget implements Target
{
    private DefaultProjectionHandler $projectionHandler;

    public function __construct(
        Projection $projection,
        ?ProjectionMetadataFactory $projectionMetadataFactory = null
    ) {
        $this->projectionHandler = new DefaultProjectionHandler(
            [$projection],
            $projectionMetadataFactory
        );
    }

    public function save(Message $message): void
    {
        $this->projectionHandler->handle($message);
    }
}
