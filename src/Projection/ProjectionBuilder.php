<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

/** @experimental */
interface ProjectionBuilder
{
    /**
     * @param array<K, Projection> $projections
     *
     * @return array<K, mixed>
     *
     * @template K of array-key
     */
    public function build(
        array $projections,
    ): array;
}
