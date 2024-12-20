<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\CommandBus;

/** @internal */
final class AggregateHandler
{
    /** @param class-string $commandClass */
    public function __construct(
        public readonly string $commandClass,
        public readonly string $method,
        public readonly bool $static,
    ) {
    }
}
