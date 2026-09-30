<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine;

use Patchlevel\EventSourcing\Metadata\Event\EventMetadataFactory;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessor;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;

use function array_keys;

/**
 * Loads only the events the subscribers are interested in, based on their subscribe metadata.
 */
final class SubscriberEventFilter implements MessageFilter
{
    public function __construct(
        private readonly EventMetadataFactory $eventMetadataFactory,
        private readonly SubscriberAccessorRepository $subscriberRepository,
    ) {
    }

    /**
     * Returns the event names (including aliases) the subscriptions are interested in.
     * If one subscriber listens to all events or has no metadata, no filter is applied.
     *
     * @param list<Subscription> $subscriptions
     *
     * @return list<string>
     */
    public function events(array $subscriptions): array
    {
        $eventNames = [];

        foreach ($subscriptions as $subscription) {
            $subscriber = $this->subscriberRepository->get($subscription->id());

            if (!$subscriber instanceof MetadataSubscriberAccessor) {
                return [];
            }

            foreach ($subscriber->events() as $event) {
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
}
