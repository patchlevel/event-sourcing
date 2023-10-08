<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Event;

use Psr\Cache\CacheItemPoolInterface;

use function assert;

final class Psr6EventRegistryFactory implements EventRegistryFactory
{
    public function __construct(
        private readonly EventRegistryFactory $eventRegistryFactory,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /** @param list<string> $paths */
    public function create(array $paths): EventRegistry
    {
        $item = $this->cache->getItem('events');

        if ($item->isHit()) {
            $data = $item->get();
            assert($data instanceof EventRegistry);

            return $data;
        }

        $registry = $this->eventRegistryFactory->create($paths);

        $item->set($registry);
        $this->cache->save($item);

        return $registry;
    }
}
