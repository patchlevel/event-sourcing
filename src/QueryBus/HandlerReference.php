<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\QueryBus;

final class HandlerReference
{
    /** @param class-string $queryClass */
    public function __construct(
        public readonly string $queryClass,
        public readonly string $method,
        public readonly bool $static,
    ) {
    }
}
