<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Repository\MessageDecorator;

use Patchlevel\EventSourcing\Attribute\Header;

/** @experimental */
#[Header('trace')]
final class TraceHeader
{
    /** @param list<array{name: string, category: string}> $traces */
    public function __construct(
        public readonly array $traces,
    ) {
    }
}
