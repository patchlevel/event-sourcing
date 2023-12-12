<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Projection;

use Patchlevel\EventSourcing\Projection\Projection;

interface ProjectionMetadataFactory
{
    /** @param class-string<Projection> $projection */
    public function metadata(string $projection): ProjectionMetadata;
}
