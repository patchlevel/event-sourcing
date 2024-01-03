<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final class Subscribe
{
    /** @param class-string $eventClass */
    public function __construct(
        public readonly string $eventClass,
    ) {
    }
}
