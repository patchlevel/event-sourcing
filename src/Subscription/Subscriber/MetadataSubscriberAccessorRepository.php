<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber;

use Patchlevel\EventSourcing\Metadata\Subscriber\AttributeSubscriberMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscriberMetadataFactory;

use function array_values;

final class MetadataSubscriberAccessorRepository implements SubscriberAccessorRepository
{
    /** @var array<string, SubscriberAccessor> */
    private array $subscribersMap = [];

    /** @param iterable<object> $subscribers */
    public function __construct(
        private readonly iterable $subscribers,
        private readonly SubscriberMetadataFactory $metadataFactory = new AttributeSubscriberMetadataFactory(),
    ) {
    }

    /** @return iterable<SubscriberAccessor> */
    public function all(): iterable
    {
        return array_values($this->subscriberAccessorMap());
    }

    public function get(string $id): SubscriberAccessor|null
    {
        $map = $this->subscriberAccessorMap();

        return $map[$id] ?? null;
    }

    /** @return array<string, SubscriberAccessor> */
    private function subscriberAccessorMap(): array
    {
        if ($this->subscribersMap !== []) {
            return $this->subscribersMap;
        }

        foreach ($this->subscribers as $subscriber) {
            $metadata = $this->metadataFactory->metadata($subscriber::class);
            $this->subscribersMap[$metadata->id] = new MetadataSubscriberAccessor($subscriber, $metadata);
        }

        return $this->subscribersMap;
    }
}
