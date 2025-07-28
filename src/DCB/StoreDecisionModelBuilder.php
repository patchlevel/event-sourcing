<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\DCB;

use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\StreamCriterion;
use Patchlevel\EventSourcing\Store\Criteria\TagCriterion;
use Patchlevel\EventSourcing\Store\TaggableDoctrineDbalStore;

/** @experimental */
final class StoreDecisionModelBuilder implements DecisionModelBuilder
{
    public function __construct(
        private TaggableDoctrineDbalStore $store,
    ) {
    }

    /** @param array<string, Projection> $projections */
    public function build(
        array $projections,
    ): DecisionModel {
        $projection = new CompositeProjection($projections);

        $stream = $this->store->load(
            new Criteria(
                new StreamCriterion('main'),
                new TagCriterion(...$projection->groupedTagFilter()),
            ),
        );

        $state = $projection->initialState();

        $highestId = 0;

        foreach ($stream as $message) {
            $highestId = $stream->index();
            $state = $projection->apply($state, $message);
        }

        return new DecisionModel(
            $state,
            new AppendCondition(
                $projection->groupedTagFilter(),
                new HighestSequenceNumber($highestId ?? 0),
            ),
        );
    }
}
