<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class PersonalData
{
    public function __construct(
        public readonly mixed $fallback = null,
    ) {
    }
}
