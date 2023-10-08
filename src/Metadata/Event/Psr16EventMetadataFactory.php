<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Event;

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
        /** @var ?EventMetadata $metadata */
        $metadata = $this->cache->get($event);

        if ($metadata !== null) {
            return $metadata;
        }

        $metadata = $this->eventMetadataFactory->metadata($event);

        $this->cache->set($event, $metadata);

        return $metadata;
    }
}
