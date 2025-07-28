<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\DCB;

/** @experimental */
final class AppendCondition
{
    /** @param list<list<string>> $tags */
    public function __construct(
        public readonly array $tags,
        public readonly HighestSequenceNumber|null $expectedHighestSequenceNumber = null,
    ) {
    }
}
