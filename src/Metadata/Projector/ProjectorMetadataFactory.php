<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Projector;

interface ProjectorMetadataFactory
{
    /**
     * @param class-string<object> $projector
     */
    public function metadata(string $projector): ProjectorMetadata;
}
