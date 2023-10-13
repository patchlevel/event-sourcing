<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\AggregateRoot;

use Patchlevel\EventSourcing\Aggregate\AggregateRoot;
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
        $item = $this->cache->getItem($aggregate);

        if ($item->isHit()) {
            /** @var AggregateRootMetadata<T> $data */
            $data = $item->get();

            return $data;
        }

        $metadata = $this->aggregateRootMetadataFactory->metadata($aggregate);

        $item->set($metadata);
        $this->cache->save($item);

        return $metadata;
    }
}
