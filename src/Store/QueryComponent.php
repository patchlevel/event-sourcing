<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store;

final class QueryComponent
{
    /** @param list<string> $tags */
    public function __construct(
        public readonly array $tags,
    ) {
    }
}
