<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\ChildAggregate;

use Patchlevel\EventSourcing\Aggregate\ChildAggregate;
use Psr\SimpleCache\CacheInterface;

final class Psr16ChildAggregateMetadataFactory implements ChildAggregateMetadataFactory
{
    public function __construct(
        private readonly ChildAggregateMetadataFactory $childAggregateMetadataFactory,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * @param class-string<T> $aggregate
     *
     * @return ChildAggregateMetadata<T>
     *
     * @template T of ChildAggregate
     */
    public function metadata(string $aggregate): ChildAggregateMetadata
    {
        /** @var ?ChildAggregateMetadata<T> $metadata */
        $metadata = $this->cache->get($aggregate);

        if ($metadata !== null) {
            return $metadata;
        }

        $metadata = $this->childAggregateMetadataFactory->metadata($aggregate);

        $this->cache->set($aggregate, $metadata);

        return $metadata;
    }
}
