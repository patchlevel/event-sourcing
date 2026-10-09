<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\AggregateRoot;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
use Patchlevel\EventSourcing\Metadata\CacheKey;
use Psr\Cache\CacheItemPoolInterface;

final class Psr6AggregateRootMetadataFactory implements AggregateRootMetadataFactory
{
    public function __construct(
        private readonly AggregateRootMetadataFactory $aggregateRootMetadataFactory,
        private readonly CacheItemPoolInterface $cache,
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
        $item = $this->cache->getItem(CacheKey::forAggregateRoot($aggregate));

        if ($item->isHit()) {
            $data = $item->get();

            if ($data instanceof AggregateRootMetadata && $data->className === $aggregate) {
                return $data;
            }
        }

        $metadata = $this->aggregateRootMetadataFactory->metadata($aggregate);

        $item->set($metadata);
        $this->cache->save($item);

        return $metadata;
    }
}
