<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Subscriber;

use Patchlevel\EventSourcing\Metadata\CacheKey;
use Psr\SimpleCache\CacheInterface;

final class Psr16SubscriberMetadataFactory implements SubscriberMetadataFactory
{
    public function __construct(
        private readonly SubscriberMetadataFactory $subscriberMetadataFactory,
        private readonly CacheInterface $cache,
    ) {
    }

    /** @param class-string $subscriber */
    public function metadata(string $subscriber): SubscriberMetadata
    {
        $metadata = $this->cache->get(CacheKey::forSubscriber($subscriber));

        if ($metadata instanceof SubscriberMetadata) {
            return $metadata;
        }

        $metadata = $this->subscriberMetadataFactory->metadata($subscriber);

        $this->cache->set(CacheKey::forSubscriber($subscriber), $metadata);

        return $metadata;
    }
}
