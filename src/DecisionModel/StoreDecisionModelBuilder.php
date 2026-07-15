<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\DecisionModel;

use Patchlevel\EventSourcing\Projection\CompositeProjection;
use Patchlevel\EventSourcing\Projection\Projection;
use Patchlevel\EventSourcing\Store\AppendCondition;
use Patchlevel\EventSourcing\Store\AppendStore;

/** @experimental */
final class StoreDecisionModelBuilder implements DecisionModelBuilder
{
    public function __construct(
        private readonly AppendStore $store,
    ) {
    }

    /** @param array<string, Projection> $projections */
    public function build(
        array $projections,
    ): DecisionModel {
        $projection = new CompositeProjection($projections);

        $query = $projection->query();
        $stream = $this->store->query($query);

        $state = $projection->initialState();

        $highestId = 0;

        foreach ($stream as $message) {
            $highestId = $stream->index() ?? 0;
            $state = $projection->apply($state, $message);
        }

        return new DecisionModel(
            $state,
            new AppendCondition(
                $query,
                $highestId,
            ),
        );
    }
}
