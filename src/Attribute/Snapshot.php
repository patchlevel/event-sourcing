<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Snapshot
{
    public function __construct(
        public readonly string $name,
        public readonly int|null $batch = null,
        public readonly string|null $version = null,
    ) {
    }
}
