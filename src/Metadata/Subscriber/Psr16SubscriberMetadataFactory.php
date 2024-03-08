<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Subscriber;

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
        /** @var ?SubscriberMetadata $metadata */
        $metadata = $this->cache->get($subscriber);

        if ($metadata !== null) {
            return $metadata;
        }

        $metadata = $this->subscriberMetadataFactory->metadata($subscriber);

        $this->cache->set($subscriber, $metadata);

        return $metadata;
    }
}
