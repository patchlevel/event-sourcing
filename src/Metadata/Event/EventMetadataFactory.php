<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Event;

interface EventMetadataFactory
{
    /**
     * @param class-string $event
     */
    public function metadata(string $event): EventMetadata;
}
