<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class ChildAggregate
{
    public function __construct(
        public readonly string $name,
    ) {
    }
}
