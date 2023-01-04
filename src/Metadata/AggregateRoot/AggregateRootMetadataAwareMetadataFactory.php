<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\AggregateRoot;

use Patchlevel\EventSourcing\Aggregate\AggregateRootInterface;
use Patchlevel\EventSourcing\Aggregate\AggregateRootMetadataAware;
use RuntimeException;

use function is_a;

final class AggregateRootMetadataAwareMetadataFactory implements AggregateRootMetadataFactory
{
    /**
     * @param class-string<AggregateRootInterface> $aggregate
     */
    public function metadata(string $aggregate): AggregateRootMetadata
    {
        if (!is_a($aggregate, AggregateRootMetadataAware::class, true)) {
            throw new RuntimeException();
        }

        return $aggregate::metadata();
    }
}
