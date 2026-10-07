<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Subscriber;

use Patchlevel\EventSourcing\Metadata\CacheKey;
use Psr\Cache\CacheItemPoolInterface;

final class Psr6SubscriberMetadataFactory implements SubscriberMetadataFactory
{
    public function __construct(
        private readonly SubscriberMetadataFactory $subscriberMetadataFactory,
        private readonly CacheItemPoolInterface $cache,
    ) {
    }

    /** @param class-string $subscriber */
    public function metadata(string $subscriber): SubscriberMetadata
    {
        $item = $this->cache->getItem(CacheKey::forSubscriber($subscriber));

        if ($item->isHit()) {
            $data = $item->get();

            if ($data instanceof SubscriberMetadata) {
                return $data;
            }
        }

        $metadata = $this->subscriberMetadataFactory->metadata($subscriber);

        $item->set($metadata);
        $this->cache->save($item);

        return $metadata;
    }
}
