<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Event;

interface EventRegistryFactory
{
    /** @param list<string> $paths */
    public function create(array $paths): EventRegistry;
}
