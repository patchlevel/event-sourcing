<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Event;

use Patchlevel\EventSourcing\Metadata\CacheKey;
use Psr\SimpleCache\CacheInterface;

final class Psr16EventMetadataFactory implements EventMetadataFactory
{
    public function __construct(
        private readonly EventMetadataFactory $eventMetadataFactory,
        private readonly CacheInterface $cache,
    ) {
    }

    /** @param class-string $event */
    public function metadata(string $event): EventMetadata
    {
        $metadata = $this->cache->get(CacheKey::forEvent($event));

        if ($metadata instanceof EventMetadata) {
            return $metadata;
        }

        $metadata = $this->eventMetadataFactory->metadata($event);

        $this->cache->set(CacheKey::forEvent($event), $metadata);

        return $metadata;
    }
}
