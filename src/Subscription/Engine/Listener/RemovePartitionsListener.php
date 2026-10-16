<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Listener;

use Patchlevel\EventSourcing\Subscription\Engine\Event\OnSubscriptionRemoved;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use function array_filter;
use function array_values;
use function count;
use function sprintf;

/**
 * Partitions belong to their main subscription, removing the main subscription removes its partitions and their inboxes.
 *
 * @internal
 */
final class RemovePartitionsListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly SubscriptionManager $subscriptionManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function onSubscriptionRemoved(OnSubscriptionRemoved $event): void
    {
        $main = $event->subscription;

        if ($main->isPartition()) {
            return;
        }

        $partitions = array_values(array_filter(
            $this->subscriptionManager->find(new SubscriptionCriteria(ids: [$main->id()], partitions: true)),
            // a store that does not know the partitions criteria would return the main subscription again
            static fn (Subscription $subscription): bool => $subscription->isPartition(),
        ));

        if ($partitions === []) {
            return;
        }

        foreach ($partitions as $partition) {
            $this->subscriptionManager->remove($partition);
            $this->eventDispatcher->dispatch(new OnSubscriptionRemoved($partition));
        }

        $this->logger?->info(sprintf(
            'Subscription Engine: Removed %d partitions of subscription "%s".',
            count($partitions),
            $main->id(),
        ));
    }

    /** @return array<class-string, string> */
    public static function getSubscribedEvents(): array
    {
        return [OnSubscriptionRemoved::class => 'onSubscriptionRemoved'];
    }
}
