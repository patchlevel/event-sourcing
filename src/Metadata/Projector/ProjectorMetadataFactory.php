<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Projector;

use Patchlevel\EventSourcing\Projection\Projector\Projector;

interface ProjectorMetadataFactory
{
    /** @param class-string<Projector> $projector */
    public function metadata(string $projector): ProjectorMetadata;
}
