<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

/** @experimental */
final class Query
{
    /** @var list<SubQuery> */
    public readonly array $subQueries;

    public function __construct(
        SubQuery ...$subQueries,
    ) {
        $this->subQueries = $subQueries;
    }

    public function add(SubQuery $subQuery): self
    {
        foreach ($this->subQueries as $query) {
            if ($query->equals($subQuery)) {
                return $this;
            }
        }

        return new self($subQuery, ...$this->subQueries);
    }
}
