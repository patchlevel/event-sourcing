<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Subscriber;

use Patchlevel\EventSourcing\Metadata\Subscriber\AttributeSubscriberMetadataFactory;
use Patchlevel\EventSourcing\Metadata\Subscriber\SubscriberMetadataFactory;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\AggregateIdArgumentResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\ArgumentResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\EventArgumentResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\MessageArgumentResolver;
use Patchlevel\EventSourcing\Subscription\Subscriber\ArgumentResolver\RecordedOnArgumentResolver;

use function array_merge;
use function array_values;

final class MetadataSubscriberAccessorRepository implements SubscriberAccessorRepository
{
    /** @var array<string, SubscriberAccessor> */
    private array $subscribersMap = [];

    /** @var list<ArgumentResolver> $argumentResolvers */
    private readonly array $argumentResolvers;

    /**
     * @param iterable<object>       $subscribers
     * @param list<ArgumentResolver> $argumentResolvers
     */
    public function __construct(
        private readonly iterable $subscribers,
        private readonly SubscriberMetadataFactory $metadataFactory = new AttributeSubscriberMetadataFactory(),
        array $argumentResolvers = [],
    ) {
        $this->argumentResolvers = array_merge(
            $argumentResolvers,
            [
                new MessageArgumentResolver(),
                new EventArgumentResolver(),
                new AggregateIdArgumentResolver(),
                new RecordedOnArgumentResolver(),
            ],
        );
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
            $this->subscribersMap[$metadata->id] = new MetadataSubscriberAccessor(
                $subscriber,
                $metadata,
                $this->argumentResolvers,
            );
        }

        return $this->subscribersMap;
    }
}
