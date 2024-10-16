<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Patchlevel\EventSourcing\Metadata\Event\EventMetadataFactory;
use Patchlevel\EventSourcing\Store\Criteria\Criteria;
use Patchlevel\EventSourcing\Store\Criteria\EventsCriterion;
use Patchlevel\EventSourcing\Store\Criteria\FromIndexCriterion;
use Patchlevel\EventSourcing\Store\Store;
use Patchlevel\EventSourcing\Store\Stream;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessor;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;

use function array_keys;

final class EventFilteredMessageLoader implements MessageLoader
{
    public function __construct(
        private readonly Store $store,
        private readonly EventMetadataFactory $eventMetadataFactory,
        private readonly SubscriberAccessorRepository $subscriberRepository,
    ) {
    }

    /** @param list<Subscription> $subscriptions */
    public function load(int $startIndex, array $subscriptions): Stream
    {
        $criteria = new Criteria(new FromIndexCriterion($startIndex));

        $events = $this->events($subscriptions);

        if ($events !== []) {
            $criteria = $criteria->add(new EventsCriterion($events));
        }

        return $this->store->load($criteria);
    }

    /**
     * @param list<Subscription> $subscriptions
     *
     * @return list<string>
     */
    private function events(array $subscriptions): array
    {
        $eventNames = [];

        foreach ($subscriptions as $subscription) {
            $subscriber =  $this->subscriberRepository->get($subscription->id());

            if (!$subscriber instanceof MetadataSubscriberAccessor) {
                return [];
            }

            $events = $subscriber->events();

            foreach ($events as $event) {
                if ($event === '*') {
                    return [];
                }

                $metadata = $this->eventMetadataFactory->metadata($event);

                $eventNames[$metadata->name] = true;

                foreach ($metadata->aliases as $alias) {
                    $eventNames[$alias] = true;
                }
            }
        }

        return array_keys($eventNames);
    }

    public function lastIndex(): int
    {
        $stream = $this->store->load(null, 1, null, true);

        return $stream->index() ?: 0;
    }
}
