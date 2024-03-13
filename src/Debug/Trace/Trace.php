<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Debug\Trace;

/** @experimental */
final class Trace
{
    public function __construct(
        public readonly string $name,
        public readonly string $category,
    ) {
    }
}
