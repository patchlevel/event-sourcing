<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Subscriber;

final class PartitionMetadata
{
    /** @param list<ArgumentMetadata> $arguments */
    public function __construct(
        public readonly string $name,
        public readonly int|null $shards = null,
        public readonly array $arguments = [],
    ) {
    }
}
