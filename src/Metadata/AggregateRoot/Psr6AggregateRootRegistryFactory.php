<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\AggregateRoot;

use Psr\Cache\CacheItemPoolInterface;

use function assert;

final class Psr6AggregateRootRegistryFactory implements AggregateRootRegistryFactory
{
    public function __construct(
        private readonly AggregateRootRegistryFactory $aggregateRootRegistryFactory,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /** @param list<string> $paths */
    public function create(array $paths): AggregateRootRegistry
    {
        $item = $this->cache->getItem('aggregate_roots');

        if ($item->isHit()) {
            $data = $item->get();
            assert($data instanceof AggregateRootRegistry);

            return $data;
        }

        $registry = $this->aggregateRootRegistryFactory->create($paths);

        $item->set($registry);
        $this->cache->save($item);

        return $registry;
    }
}
