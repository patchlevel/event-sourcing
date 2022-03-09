<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

interface ProjectionMetadataFactory
{
    public function metadata(Projection $projection): ProjectionMetadata;
}
