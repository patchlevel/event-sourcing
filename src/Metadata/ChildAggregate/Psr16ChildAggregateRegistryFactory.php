<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\ChildAggregate;

use Psr\SimpleCache\CacheInterface;

final class Psr16ChildAggregateRegistryFactory implements ChildAggregateRegistryFactory
{
    private const CACHE_KEY = 'child_aggregate_registry';

    public function __construct(
        private readonly ChildAggregateRegistryFactory $childAggregateRegistryFactory,
        private readonly CacheInterface $cache,
    ) {
    }

    /** @param list<string> $paths */
    public function create(array $paths): ChildAggregateRegistry
    {
        /** @var ?ChildAggregateRegistry $registry */
        $registry = $this->cache->get(self::CACHE_KEY);

        if ($registry !== null) {
            return $registry;
        }

        $registry = $this->childAggregateRegistryFactory->create($paths);

        $this->cache->set(self::CACHE_KEY, $registry);

        return $registry;
    }
}
