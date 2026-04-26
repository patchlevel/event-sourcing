<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Listener;

use Patchlevel\EventSourcing\Subscription\Engine\Event\OnCommand;
use Patchlevel\EventSourcing\Subscription\Engine\MessageLoader;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\RunMode;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Subscriber\MetadataSubscriberAccessor;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Psr\Log\LoggerInterface;

use function array_values;
use function sprintf;

/** @internal */
final class DiscoverListener
{
    public function __construct(
        private readonly MessageLoader $messageLoader,
        private readonly SubscriptionManager $subscriptionManager,
        private readonly SubscriberAccessorRepository $subscriberRepository,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function __invoke(OnCommand $event): void
    {
        $command = $event->command;

        // todo define when to discover

        $subscriptions = $this->subscriptionManager->find(new SubscriptionCriteria(
            // ids: $command->ids,
            // groups: $command->groups,
        ));

        $latestIndex = null;

        foreach ($this->subscriberRepository->all() as $subscriber) {
            foreach ($subscriptions as $subscription) {
                if ($subscription->id() === $subscriber->metadata()->id) {
                    continue 2;
                }
            }

            $subscription = new Subscription(
                $subscriber->metadata()->id,
                $subscriber->metadata()->group,
                $subscriber->metadata()->runMode,
                cleanupTasks: $this->cleanupTasks($subscriber),
            );

            if ($subscriber->setupMethod() === null && $subscriber->metadata()->runMode === RunMode::FromNow) {
                if ($latestIndex === null) {
                    $latestIndex = $this->messageLoader->lastIndex();
                }

                $subscription->changePosition($latestIndex);
                $subscription->active();
            }

            $this->subscriptionManager->add($subscription);

            $this->logger?->info(
                sprintf(
                    'Subscription Engine: New Subscriber "%s" was found and added to the subscription store.',
                    $subscriber->metadata()->id,
                ),
            );
        }

        $this->subscriptionManager->flush();
    }

    /** @return list<object>|null */
    private function cleanupTasks(MetadataSubscriberAccessor $subscriber): array|null
    {
        $method = $subscriber->cleanupMethod();

        if (!$method) {
            return null;
        }

        return array_values([...$method()]);
    }
}
