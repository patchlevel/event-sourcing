<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Store\Header;

use Patchlevel\EventSourcing\Attribute\Header;

/**
 * @experimental
 * @psalm-immutable
 */
#[Header('tags')]
final class TagsHeader
{
    /** @param list<string> $tags */
    public function __construct(
        public readonly array $tags,
    ) {
    }
}
