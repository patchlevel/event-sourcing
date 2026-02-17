<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;
use Patchlevel\EventSourcing\Aggregate\AggregateRoot;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class SharedApplyContext
{
    /** @param list<class-string<AggregateRoot>> $aggregates */
    public function __construct(
        public array $aggregates,
    ) {
    }
}
