<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\AggregateRoot;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;

interface AggregateRootMetadataFactory
{
    /**
     * @param class-string<T> $aggregate
     *
     * @return AggregateRootMetadata<T>
     *
     * @template T of AggregateRoot
     */
    public function metadata(string $aggregate): AggregateRootMetadata;
}
