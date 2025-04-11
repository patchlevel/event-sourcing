<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class Answer
{
    /** @param class-string|null $queryClass */
    public function __construct(
        public readonly string|null $queryClass = null,
    ) {
    }
}
