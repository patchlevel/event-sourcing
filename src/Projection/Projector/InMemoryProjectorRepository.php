<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection\Projector;

final class InMemoryProjectorRepository implements ProjectorRepository
{
    /** @param iterable<Projector> $projectors */
    public function __construct(
        private readonly iterable $projectors = [],
    ) {
    }

    /** @return list<Projector> */
    public function projectors(): array
    {
        return [...$this->projectors];
    }
}
