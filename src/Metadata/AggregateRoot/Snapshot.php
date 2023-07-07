<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\AggregateRoot;

final class Snapshot
{
    public function __construct(
        public readonly string $store,
        public readonly int|null $batch = null,
        public readonly string|null $version = null,
    ) {
    }
}
