<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\AggregateRoot;

use Psr\SimpleCache\CacheInterface;

final class Psr16AggregateRootRegistryFactory implements AggregateRootRegistryFactory
{
    public function __construct(
        private readonly AggregateRootRegistryFactory $aggregateRootRegistryFactory,
        private readonly CacheInterface $cache,
    ) {
    }

    /** @param list<string> $paths */
    public function create(array $paths): AggregateRootRegistry
    {
        /** @var ?AggregateRootRegistry $registry */
        $registry = $this->cache->get('aggregate_roots');

        if ($registry !== null) {
            return $registry;
        }

        $registry = $this->aggregateRootRegistryFactory->create($paths);

        $this->cache->set('aggregate_roots', $registry);

        return $registry;
    }
}
