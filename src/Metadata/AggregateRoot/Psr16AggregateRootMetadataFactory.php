<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\AggregateRoot;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Metadata\CacheKey;
use Psr\SimpleCache\CacheInterface;

final class Psr16AggregateRootMetadataFactory implements AggregateRootMetadataFactory
{
    public function __construct(
        private readonly AggregateRootMetadataFactory $aggregateRootMetadataFactory,
        private readonly CacheInterface $cache,
    ) {
    }

    /**
     * @param class-string<T> $aggregate
     *
     * @return AggregateRootMetadata<T>
     *
     * @template T of AggregateRoot
     */
    public function metadata(string $aggregate): AggregateRootMetadata
    {
        $metadata = $this->cache->get(CacheKey::forAggregateRoot($aggregate));

        if ($metadata instanceof AggregateRootMetadata && $metadata->className === $aggregate) {
            return $metadata;
        }

        $metadata = $this->aggregateRootMetadataFactory->metadata($aggregate);

        $this->cache->set(CacheKey::forAggregateRoot($aggregate), $metadata);

        return $metadata;
    }
}
