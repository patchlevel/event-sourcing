<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Event;

use Psr\SimpleCache\CacheInterface;

final class Psr16EventRegistryFactory implements EventRegistryFactory
{
    public function __construct(
        private readonly EventRegistryFactory $eventRegistryFactory,
        private readonly CacheInterface $cache,
    ) {
    }

    /** @param list<string> $paths */
    public function create(array $paths): EventRegistry
    {
        /** @var ?EventRegistry $registry */
        $registry = $this->cache->get('events');

        if ($registry !== null) {
            return $registry;
        }

        $registry = $this->eventRegistryFactory->create($paths);

        $this->cache->set('events', $registry);

        return $registry;
    }
}
