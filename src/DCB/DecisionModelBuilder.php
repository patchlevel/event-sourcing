<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\DCB;

/** @experimental */
interface DecisionModelBuilder
{
    /** @param array<string, Projection> $projections */
    public function build(
        array $projections,
    ): DecisionModel;
}
