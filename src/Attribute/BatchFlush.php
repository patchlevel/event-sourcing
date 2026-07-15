<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class BatchFlush
{
    public function __construct(
        public readonly int|null $afterMessages = null,
    ) {
    }
}
