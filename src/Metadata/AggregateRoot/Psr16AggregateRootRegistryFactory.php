<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\AggregateRoot;

use Psr\SimpleCache\CacheInterface;

final class Psr16AggregateRootRegistryFactory implements AggregateRootRegistryFactory
{
    private const CACHE_KEY = 'aggregate_root_registry';

    public function __construct(
        private readonly AggregateRootRegistryFactory $aggregateRootRegistryFactory,
        private readonly CacheInterface $cache,
    ) {
    }

    /** @param list<string> $paths */
    public function create(array $paths): AggregateRootRegistry
    {
        /** @var ?AggregateRootRegistry $registry */
        $registry = $this->cache->get(self::CACHE_KEY);

        if ($registry !== null) {
            return $registry;
        }

        $registry = $this->aggregateRootRegistryFactory->create($paths);

        $this->cache->set(self::CACHE_KEY, $registry);

        return $registry;
    }
}
