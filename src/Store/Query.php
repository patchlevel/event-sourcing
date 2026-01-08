<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

use function array_values;

/** @experimental */
final class Query
{
    /** @var list<SubQuery> */
    public readonly array $subQueries;

    public function __construct(
        SubQuery ...$subQueries,
    ) {
        $this->subQueries = array_values($subQueries);
    }

    public function add(SubQuery $subQuery): self
    {
        return new self(...[...$this->subQueries, $subQuery]);
    }

    /**
     * Optimize the query by removing sub-queries that are included in other sub-queries.
     */
    public function optimize(): Query
    {
        $queries = $this->subQueries;

        foreach ($queries as $key => $a) {
            foreach ($queries as $b) {
                if ($a === $b) {
                    continue;
                }

                if ($b->empty() && !$b->onlyLastEvent) {
                    return new self();
                }

                if ($b->includes($a)) {
                    unset($queries[$key]);
                    continue 2;
                }
            }
        }

        return new self(...$queries);
    }
}
