<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\AggregateRoot;

final class AggregateRootMetadata
{
    public function __construct(
        public readonly string $name,
        /** @var array<class-string, string> */
        public readonly array $applyMethods,
        /** @var array<class-string, true> */
        public readonly array $suppressEvents,
        public readonly bool $suppressAll,
        public readonly string|null $snapshotStore,
        public readonly int|null $snapshotBatch,
        public readonly string|null $snapshotVersion = null,
    ) {
    }
}
