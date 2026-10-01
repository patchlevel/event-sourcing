<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Patchlevel\EventSourcing\Metadata\Event\EventMetadataFactory;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\Stream;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;

/** @deprecated use StoreMessageLoader with SubscriberEventFilter instead */
final class EventFilteredStoreMessageLoader implements MessageLoader
{
    private readonly StoreMessageLoader $loader;

    public function __construct(
        Store $store,
        EventMetadataFactory $eventMetadataFactory,
        SubscriberAccessorRepository $subscriberRepository,
    ) {
        $this->loader = new StoreMessageLoader(
            $store,
            new SubscriberEventFilter($eventMetadataFactory, $subscriberRepository),
        );
    }

    /** @param list<Subscription> $subscriptions */
    public function load(int $startIndex, array $subscriptions): Stream
    {
        return $this->loader->load($startIndex, $subscriptions);
    }

    public function lastIndex(): int
    {
        return $this->loader->lastIndex();
    }
}
