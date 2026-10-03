<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\Store\AppendStore;

/** @experimental */
final class StoreProjectionBuilder implements ProjectionBuilder
{
    public function __construct(
        private AppendStore $store,
    ) {
    }

    /**
     * @param array<string, Projection> $projections
     *
     * @return array<string, mixed>
     */
    public function build(
        array $projections,
    ): array {
        $projection = new CompositeProjection($projections);

        $query = $projection->query();
        $stream = $this->store->query($query);

        $state = $projection->initialState();

        foreach ($stream as $message) {
            $state = $projection->apply($state, $message);
        }

        return $state;
    }
}
