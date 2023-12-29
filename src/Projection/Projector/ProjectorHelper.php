<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projector;

use Patchlevel\EventSourcing\Metadata\Projector\AttributeProjectorMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Projector\ProjectorMetadataFactory;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionId;

final class ProjectorHelper
{
    public function __construct(
        private readonly ProjectorMetadataFactory $metadataFactory = new AttributeProjectorMetadataFactory(),
    ) {
    }

    public function name(object $projector): string
    {
        $metadata = $this->metadataFactory->metadata($projector::class);

        return $metadata->name;
    }

    public function version(object $projector): int
    {
        $metadata = $this->metadataFactory->metadata($projector::class);

        return $metadata->version;
    }

    public function projectionId(object $projector): ProjectionId
    {
        return new ProjectionId(
            $this->name($projector),
            $this->version($projector),
        );
    }
}
