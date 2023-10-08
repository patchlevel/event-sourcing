<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Event;

use Psr\Cache\CacheItemPoolInterface;

use function assert;

final class Psr6EventMetadataFactory implements EventMetadataFactory
{
    public function __construct(
        private readonly EventMetadataFactory $eventMetadataFactory,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /** @param class-string $event */
    public function metadata(string $event): EventMetadata
    {
        $item = $this->cache->getItem($event);

        if ($item->isHit()) {
            $data = $item->get();
            assert($data instanceof EventMetadata);

            return $data;
        }

        $metadata = $this->eventMetadataFactory->metadata($event);

        $item->set($metadata);
        $this->cache->save($item);

        return $metadata;
    }
}
