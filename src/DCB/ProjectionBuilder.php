<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\DCB;

/** @experimental */
interface ProjectionBuilder
{
    /**
     * @param array<string, Projection> $projections
     *
     * @return array<string, mixed>
     */
    public function build(
        array $projections,
    ): array;
}
