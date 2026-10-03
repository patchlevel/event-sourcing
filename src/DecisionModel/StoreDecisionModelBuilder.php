<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\DecisionModel;

use Patchlevel\EventSourcing\Projection\CompositeProjection;
use Patchlevel\EventSourcing\Projection\Projection;
use Patchlevel\EventSourcing\Projection\SplitStreamPositions;
use Patchlevel\EventSourcing\Store\AppendCondition;
use Patchlevel\EventSourcing\Store\AppendStore;

use function max;
use function min;

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
        $from = SplitStreamPositions::resolve($this->store, $projections);
        $projection = new CompositeProjection($projections, $from);

        $query = $projection->query();
        $stream = $this->store->query($query, $from === [] ? 0 : min($from));

        $state = $projection->initialState();

        $highestId = 0;

        foreach ($stream as $message) {
            $highestId = max($highestId, $stream->index() ?? 0);
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
