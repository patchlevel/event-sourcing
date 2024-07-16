<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\ChildAggregate;

use Patchlevel\EventSourcing\Aggregate\ChildAggregate;

/** @template T of ChildAggregate */
final class ChildAggregateMetadata
{
    public function __construct(
        /** @var class-string<T> */
        public readonly string $className,
        /** @var array<class-string, string> */
        public readonly array $applyMethods,
        /** @var array<class-string, true> */
        public readonly array $suppressEvents,
        public readonly bool $suppressAll,
    ) {
    }
}
