<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Pipeline\Target;

use Patchlevel\EventSourcing\EventBus\Message;
use Patchlevel\EventSourcing\Metadata\Projection\ProjectionMetadataFactory;
use Patchlevel\EventSourcing\Projection\MetadataAwareProjectionHandler;
use Patchlevel\EventSourcing\Projection\Projection;

/**
 * @deprecated use ProjectorTarget
 */
final class ProjectionTarget implements Target
{
    private MetadataAwareProjectionHandler $projectionHandler;

    public function __construct(
        Projection $projection,
        ?ProjectionMetadataFactory $projectionMetadataFactory = null
    ) {
        $this->projectionHandler = new MetadataAwareProjectionHandler(
            [$projection],
            $projectionMetadataFactory
        );
    }

    public function save(Message $message): void
    {
        $this->projectionHandler->handle($message);
    }
}
