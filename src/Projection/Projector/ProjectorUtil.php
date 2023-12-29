<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projector;

use Patchlevel\EventSourcing\Metadata\Projector\AttributeProjectorMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Projector\ProjectorMetadataFactory;
use Patchlevel\EventSourcing\Projection\Projection\ProjectionId;

trait ProjectorUtil
{
    private static ProjectorMetadataFactory|null $metadataFactory = null;

    public static function setMetadataFactory(ProjectorMetadataFactory $metadataFactory): void
    {
        self::$metadataFactory = $metadataFactory;
    }

    private static function metadataFactory(): ProjectorMetadataFactory
    {
        if (self::$metadataFactory === null) {
            self::$metadataFactory = new AttributeProjectorMetadataFactory();
        }

        return self::$metadataFactory;
    }

    private function projectionName(): string
    {
        return (new ProjectorHelper(self::metadataFactory()))->name($this);
    }

    private function projectionVersion(): int
    {
        return (new ProjectorHelper(self::metadataFactory()))->version($this);
    }

    private function projectionId(): ProjectionId
    {
        return (new ProjectorHelper(self::metadataFactory()))->projectionId($this);
    }
}
