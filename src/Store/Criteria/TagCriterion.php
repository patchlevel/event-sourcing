<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store\Criteria;

use function array_values;

/** @experimental */
final class TagCriterion
{
    /** @var list<list<string>> */
    public readonly array $tags;

    /** @param list<string> ...$tags */
    public function __construct(
        array ...$tags,
    ) {
        $this->tags = array_values($tags);
    }
}
