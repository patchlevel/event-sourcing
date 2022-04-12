<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\AggregateRoot;

interface AggregateRootRegistryFactory
{
    /**
     * @param list<string> $paths
     */
    public function create(array $paths): AggregateRootRegistry;
}
