<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Projector;

use Psr\Cache\CacheItemPoolInterface;

use function assert;

final class Psr6ProjectorMetadataFactory implements ProjectorMetadataFactory
{
    public function __construct(
        private readonly ProjectorMetadataFactory $projectorMetadataFactory,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /** @param class-string $projector */
    public function metadata(string $projector): ProjectorMetadata
    {
        $item = $this->cache->getItem($projector);

        if ($item->isHit()) {
            $data = $item->get();
            assert($data instanceof ProjectorMetadata);

            return $data;
        }

        $metadata = $this->projectorMetadataFactory->metadata($projector);

        $item->set($metadata);
        $this->cache->save($item);

        return $metadata;
    }
}
