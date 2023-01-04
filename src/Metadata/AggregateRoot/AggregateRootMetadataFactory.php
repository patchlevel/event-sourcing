<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\AggregateRoot;

use Patchlevel\EventSourcing\Aggregate\AggregateRootInterface;

interface AggregateRootMetadataFactory
{
    /**
     * @param class-string<AggregateRootInterface> $aggregate
     */
    public function metadata(string $aggregate): AggregateRootMetadata;
}
