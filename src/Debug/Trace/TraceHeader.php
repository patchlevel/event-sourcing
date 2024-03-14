<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Debug\Trace;

/** @experimental */
final class TraceHeader
{
    /** @param list<array{name: string, category: string}> $traces */
    public function __construct(
        public readonly array $traces,
    ) {
    }
}
