<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\Store\AppendStore;

use function min;

/** @experimental */
final class StoreProjectionBuilder implements ProjectionBuilder
{
    public function __construct(
        private AppendStore $store,
    ) {
    }

    /**
     * @param array<K, Projection> $projections
     *
     * @return array<K, mixed>
     *
     * @template K of array-key
     */
    public function build(
        array $projections,
    ): array {
        $from = CheckpointPositions::resolve($this->store, $projections);
        $projection = new CompositeProjection($projections, $from);

        $query = $projection->query();
        $stream = $this->store->query($query, $from === [] ? 0 : min($from));

        $state = $projection->initialState();

        foreach ($stream as $message) {
            $state = $projection->apply($state, $message);
        }

        /** @var array<K, mixed> $result */
        $result = $state;

        return $result;
    }
}
