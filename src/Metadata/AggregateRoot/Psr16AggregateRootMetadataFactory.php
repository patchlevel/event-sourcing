<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\AggregateRoot;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
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
        /** @var ?AggregateRootMetadata<T> $metadata */
        $metadata = $this->cache->get($aggregate);

        if ($metadata !== null) {
            return $metadata;
        }

        $metadata = $this->aggregateRootMetadataFactory->metadata($aggregate);

        $this->cache->set($aggregate, $metadata);

        return $metadata;
    }
}
