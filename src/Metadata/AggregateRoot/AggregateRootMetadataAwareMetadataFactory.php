<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\AggregateRoot;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Aggregate\AggregateRootMetadataAware;

use function is_a;

final class AggregateRootMetadataAwareMetadataFactory implements AggregateRootMetadataFactory
{
    /**
     * @param class-string<AggregateRoot> $aggregate
     */
    public function metadata(string $aggregate): AggregateRootMetadata
    {
        if (!is_a($aggregate, AggregateRootMetadataAware::class, true)) {
            throw new AggregateWithoutMetadataAware($aggregate);
        }

        return $aggregate::metadata();
    }
}
