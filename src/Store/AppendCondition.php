<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

/**
 * The append fails if the store contains an event matching the query with an index higher than `after`.
 * `after` is the highest index the caller was aware of while building the decision model.
 * With `after` 0, no event may match the query at all.
 *
 * @experimental
 */
final class AppendCondition
{
    /** @param positive-int|0 $after */
    public function __construct(
        public readonly Query $query,
        public readonly int $after = 0,
    ) {
    }
}
