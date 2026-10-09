<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber;

use Patchlevel\EventSourcing\Metadata\Subscriber\AttributeSubscriberMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscriberMetadataFactory;

use function array_key_exists;
use function array_values;

final class MetadataSubscriberAccessorRepository implements SubscriberAccessorRepository
{
    /** @var array<string, MetadataSubscriberAccessor> */
    private array $subscribersMap = [];

    /** @param iterable<object> $subscribers */
    public function __construct(
        private readonly iterable $subscribers,
        private readonly SubscriberMetadataFactory $metadataFactory = new AttributeSubscriberMetadataFactory(),
    ) {
    }

    /** @return iterable<MetadataSubscriberAccessor> */
    public function all(): iterable
    {
        return array_values($this->subscriberAccessorMap());
    }

    public function get(string $id): MetadataSubscriberAccessor|null
    {
        $map = $this->subscriberAccessorMap();

        return $map[$id] ?? null;
    }

    /** @return array<string, MetadataSubscriberAccessor> */
    private function subscriberAccessorMap(): array
    {
        if ($this->subscribersMap !== []) {
            return $this->subscribersMap;
        }

        foreach ($this->subscribers as $subscriber) {
            $metadata = $this->metadataFactory->metadata($subscriber::class);

            if (array_key_exists($metadata->id, $this->subscribersMap)) {
                throw new DuplicateSubscriberId($metadata->id);
            }

            $this->subscribersMap[$metadata->id] = new MetadataSubscriberAccessor(
                $subscriber,
                $metadata,
            );
        }

        return $this->subscribersMap;
    }
}
