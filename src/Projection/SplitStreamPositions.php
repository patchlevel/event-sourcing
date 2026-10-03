<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Projection;

use Patchlevel\EventSourcing\Store\AppendStore;
use Patchlevel\EventSourcing\Store\Query;
use Patchlevel\EventSourcing\Store\SubQuery;

use function array_map;
use function array_values;
use function max;

/** @internal */
final class SplitStreamPositions
{
    /**
     * Returns for each projection the index from which on it needs events.
     * That is the index of its last split event, or 0 if it has none.
     *
     * @param array<string, Projection> $projections
     *
     * @return array<string, positive-int|0>
     */
    public static function resolve(AppendStore $store, array $projections): array
    {
        $positions = array_map(static fn (): int => 0, $projections);
        $lookups = [];

        foreach ($projections as $name => $projection) {
            if (!$projection instanceof SplitStreamProvider || !$projection instanceof SubQueryProvider) {
                continue;
            }

            $splitEvents = $projection->splitEvents();

            if ($splitEvents === []) {
                continue;
            }

            $subQuery = $projection->subQuery();

            $lookups[$name] = new SubQuery(
                $subQuery->tags,
                $splitEvents,
                $subQuery->streamName,
                true,
            );
        }

        if ($lookups === []) {
            return $positions;
        }

        foreach ($store->query(new Query(...array_values($lookups))) as $index => $message) {
            foreach ($lookups as $name => $lookup) {
                if (!$lookup->match($message)) {
                    continue;
                }

                $positions[$name] = max($positions[$name], $index);
            }
        }

        return $positions;
    }
}
