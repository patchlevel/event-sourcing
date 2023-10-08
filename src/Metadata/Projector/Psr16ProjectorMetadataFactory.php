<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Projector;

use Patchlevel\EventSourcing\Projection\Projector\Projector;
use Psr\SimpleCache\CacheInterface;

final class Psr16ProjectorMetadataFactory implements ProjectorMetadataFactory
{
    public function __construct(
        private readonly ProjectorMetadataFactory $projectorMetadataFactory,
        private readonly CacheInterface $cache,
    ) {
    }

    /** @param class-string<Projector> $projector */
    public function metadata(string $projector): ProjectorMetadata
    {
        /** @var ?ProjectorMetadata $metadata */
        $metadata = $this->cache->get($projector);

        if ($metadata !== null) {
            return $metadata;
        }

        $metadata = $this->projectorMetadataFactory->metadata($projector);

        $this->cache->set($projector, $metadata);

        return $metadata;
    }
}
