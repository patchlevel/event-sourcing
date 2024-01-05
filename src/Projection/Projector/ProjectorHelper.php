<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projector;

use Patchlevel\EventSourcing\Metadata\Projector\AttributeProjectorMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Projector\ProjectorMetadata;
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
        return $this->getProjectorMetadata($projector)->name;
    }

    public function version(object $projector): int
    {
        return $this->getProjectorMetadata($projector)->version;
    }

    public function projectionId(object $projector): ProjectionId
    {
        $metadata = $this->getProjectorMetadata($projector);

        return new ProjectionId($metadata->name, $metadata->version);
    }

    public function getProjectorMetadata(object $projector): ProjectorMetadata
    {
        return $this->metadataFactory->metadata($projector::class);
    }
}
