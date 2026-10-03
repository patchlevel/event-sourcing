<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store\Criteria;

/** @experimental */
final class TagCriterion
{
    /** @param list<string> $tags */
    public function __construct(
        public readonly array $tags,
    ) {
    }
}
