<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\DCB;

use Patchlevel\EventSourcing\Store\AppendCondition;
use Patchlevel\EventSourcing\Store\AppendStore;
use Patchlevel\EventSourcing\Store\Query;
use Patchlevel\EventSourcing\Store\QueryComponent;

use function array_map;

/** @experimental */
final class StoreDecisionModelBuilder implements DecisionModelBuilder
{
    public function __construct(
        private AppendStore $store,
    ) {
    }

    /** @param array<string, Projection> $projections */
    public function build(
        array $projections,
    ): DecisionModel {
        $projection = new CompositeProjection($projections);

        $query = new Query(...array_map(
            static fn (array $tags) => new QueryComponent($tags),
            $projection->groupedTagFilter(),
        ));

        $stream = $this->store->query($query);

        $state = $projection->initialState();

        $highestId = 0;

        foreach ($stream as $message) {
            $highestId = $stream->index();
            $state = $projection->apply($state, $message);
        }

        return new DecisionModel(
            $state,
            new AppendCondition(
                $query,
                $highestId ?? 0,
            ),
        );
    }
}
