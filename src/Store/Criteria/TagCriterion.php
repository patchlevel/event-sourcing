<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store\Criteria;

use InvalidArgumentException;

use function array_unique;
use function array_values;

final class TagCriterion
{
    /** @var list<string> */
    public readonly array $tags;

    public function __construct(
        string ...$tags,
    ) {
        $this->tags = array_values(array_unique($tags));

        if ($this->tags === []) {
            throw new InvalidArgumentException('At least one tag must be provided.');
        }
    }
}
