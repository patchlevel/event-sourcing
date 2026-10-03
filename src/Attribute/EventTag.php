<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_METHOD)]
final class EventTag
{
    public function __construct(
        public readonly string|null $prefix = null,
        public readonly string|null $hash = null,
    ) {
    }
}
