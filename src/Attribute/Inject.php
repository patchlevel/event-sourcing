<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER)]
final class Inject
{
    public function __construct(
        public readonly string|null $service = null,
    ) {
    }
}
