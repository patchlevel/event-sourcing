<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\ChildAggregate;

interface ChildAggregateRegistryFactory
{
    /** @param list<string> $paths */
    public function create(array $paths): ChildAggregateRegistry;
}
