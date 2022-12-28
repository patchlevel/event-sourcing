<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Projection;

use Patchlevel\EventSourcing\Projection\Projector\Projector;

interface ProjectionMetadataFactory
{
    /**
     * @param class-string<Projector> $projection
     */
    public function metadata(string $projection): ProjectionMetadata;
}
