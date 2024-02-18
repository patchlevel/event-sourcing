<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Projector
{
    public function __construct(
        public readonly string $id,
        public readonly string $group = 'default',
        public readonly bool $fromNow = false,
    ) {
    }
}
