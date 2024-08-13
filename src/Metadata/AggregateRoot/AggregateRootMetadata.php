<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\AggregateRoot;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;

/** @template T of AggregateRoot */
final class AggregateRootMetadata
{
    public function __construct(
        /** @var class-string<T> */
        public readonly string $className,
        public readonly string $name,
        public readonly string $idProperty,
        /** @var array<class-string, string> */
        public readonly array $applyMethods,
        /** @var array<class-string, true> */
        public readonly array $suppressEvents,
        public readonly bool $suppressAll,
        public readonly Snapshot|null $snapshot,
        /** @var list<string> */
        public readonly array $childAggregates = [],
    ) {
    }
}
