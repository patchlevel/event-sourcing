<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\ChildAggregate;

use Patchlevel\EventSourcing\Aggregate\ChildAggregate;
use Psr\Cache\CacheItemPoolInterface;

final class Psr6ChildAggregateMetadataFactory implements ChildAggregateMetadataFactory
{
    public function __construct(
        private readonly ChildAggregateMetadataFactory $childAggregateMetadataFactory,
        private readonly CacheItemPoolInterface $cache,
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
        $item = $this->cache->getItem($aggregate);

        if ($item->isHit()) {
            /** @var ChildAggregateMetadata<T> $data */
            $data = $item->get();

            return $data;
        }

        $metadata = $this->childAggregateMetadataFactory->metadata($aggregate);

        $item->set($metadata);
        $this->cache->save($item);

        return $metadata;
    }
}
