<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\ChildAggregate;

use Psr\Cache\CacheItemPoolInterface;

use function assert;

final class Psr6ChildAggregateRegistryFactory implements ChildAggregateRegistryFactory
{
    private const CACHE_KEY = 'child_aggregate_registry';

    public function __construct(
        private readonly ChildAggregateRegistryFactory $childAggregateRegistryFactory,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /** @param list<string> $paths */
    public function create(array $paths): ChildAggregateRegistry
    {
        $item = $this->cache->getItem(self::CACHE_KEY);

        if ($item->isHit()) {
            $data = $item->get();
            assert($data instanceof ChildAggregateRegistry);

            return $data;
        }

        $registry = $this->childAggregateRegistryFactory->create($paths);

        $item->set($registry);
        $this->cache->save($item);

        return $registry;
    }
}
