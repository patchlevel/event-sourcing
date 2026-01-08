<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\DecisionModel;

use Patchlevel\EventSourcing\Projection\Projection;

/** @experimental */
interface DecisionModelBuilder
{
    /** @param array<string, Projection> $projections */
    public function build(
        array $projections,
    ): DecisionModel;
}
