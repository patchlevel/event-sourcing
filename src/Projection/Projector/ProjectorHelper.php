<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projector;

use Patchlevel\EventSourcing\Metadata\Projector\AttributeProjectorMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Projector\ProjectorMetadata;
use Patchlevel\EventSourcing\Metadata\Projector\ProjectorMetadataFactory;

final class ProjectorHelper
{
    public function __construct(
        private readonly ProjectorMetadataFactory $metadataFactory = new AttributeProjectorMetadataFactory(),
    ) {
    }

    public function projectorId(object $projector): string
    {
        return $this->getProjectorMetadata($projector)->id;
    }

    private function getProjectorMetadata(object $projector): ProjectorMetadata
    {
        return $this->metadataFactory->metadata($projector::class);
    }
}
