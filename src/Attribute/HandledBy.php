<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;

#[Attribute(Attribute::TARGET_CLASS)]
final class HandledBy
{
    /** @param class-string<AggregateRoot> $aggregateClass */
    public function __construct(
        public readonly string $aggregateClass,
    ) {
    }
}
