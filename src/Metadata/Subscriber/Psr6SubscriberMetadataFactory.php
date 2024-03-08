<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Metadata\Subscriber;

use Psr\Cache\CacheItemPoolInterface;

use function assert;

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
        $item = $this->cache->getItem($subscriber);

        if ($item->isHit()) {
            $data = $item->get();
            assert($data instanceof SubscriberMetadata);

            return $data;
        }

        $metadata = $this->subscriberMetadataFactory->metadata($subscriber);

        $item->set($metadata);
        $this->cache->save($item);

        return $metadata;
    }
}
