<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Listener;

use Patchlevel\EventSourcing\Subscription\Engine\Event\OnSubscriptionRemoved;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\SubscriptionType;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use function count;
use function sprintf;

/** @internal */
final class PartitionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly SubscriptionManager $subscriptionManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function onSubscriptionRemoved(OnSubscriptionRemoved $event): void
    {
        $root = $event->subscription;

        if ($root->type() !== SubscriptionType::Main) {
            return;
        }

        $children = $this->subscriptionManager->find(new SubscriptionCriteria(
            subscriberIds: [$root->subscriberId()],
            types: [SubscriptionType::Partition],
        ));

        foreach ($children as $child) {
            $this->subscriptionManager->remove($child);
            $this->eventDispatcher->dispatch(new OnSubscriptionRemoved($child));
        }

        if ($children === []) {
            return;
        }

        $this->logger?->info(sprintf(
            'Subscription Engine: Removed %d partition rows of subscription "%s".',
            count($children),
            $root->id(),
        ));
    }

    /** @return array<class-string, string|array{string, int}> */
    public static function getSubscribedEvents(): array
    {
        return [OnSubscriptionRemoved::class => 'onSubscriptionRemoved'];
    }
}
