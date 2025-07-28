<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store\Header;

/**
 * @experimental
 * @psalm-immutable
 */
final class TagsHeader
{
    /** @param list<string> $tags */
    public function __construct(
        public readonly array $tags,
    ) {
    }
}
