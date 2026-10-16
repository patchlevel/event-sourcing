<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store\Criteria;

use function array_values;

/** Matches only the messages with one of the given indices. */
final class IndexCriterion
{
    /** @var list<int> */
    public readonly array $indices;

    public function __construct(int ...$indices)
    {
        $this->indices = array_values($indices);
    }
}
