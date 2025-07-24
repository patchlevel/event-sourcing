<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store\Header;

/** @psalm-immutable */
class TagsHeader
{
    /** @param list<string> $tags */
    public function __construct(
        public readonly array $tags,
    ) {
    }
}
