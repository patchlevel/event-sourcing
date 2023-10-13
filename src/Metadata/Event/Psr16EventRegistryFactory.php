<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Event;

use Psr\SimpleCache\CacheInterface;

final class Psr16EventRegistryFactory implements EventRegistryFactory
{
    private const CACHE_KEY = 'event_registry';

    public function __construct(
        private readonly EventRegistryFactory $eventRegistryFactory,
        private readonly CacheInterface $cache,
    ) {
    }

    /** @param list<string> $paths */
    public function create(array $paths): EventRegistry
    {
        /** @var ?EventRegistry $registry */
        $registry = $this->cache->get(self::CACHE_KEY);

        if ($registry !== null) {
            return $registry;
        }

        $registry = $this->eventRegistryFactory->create($paths);

        $this->cache->set(self::CACHE_KEY, $registry);

        return $registry;
    }
}
