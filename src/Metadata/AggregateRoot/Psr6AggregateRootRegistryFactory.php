<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\AggregateRoot;

use Psr\Cache\CacheItemPoolInterface;

use function assert;

final class Psr6AggregateRootRegistryFactory implements AggregateRootRegistryFactory
{
    private const CACHE_KEY = 'aggregate_root_registry';

    public function __construct(
        private readonly AggregateRootRegistryFactory $aggregateRootRegistryFactory,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /** @param list<string> $paths */
    public function create(array $paths): AggregateRootRegistry
    {
        $item = $this->cache->getItem(self::CACHE_KEY);

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
