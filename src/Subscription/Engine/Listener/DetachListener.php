<?php

declare(strict_types=1);

namespace Patchlevel\EventSourcing\Subscription\Engine\Listener;

use Patchlevel\EventSourcing\Subscription\Engine\Command\Run;
use Patchlevel\EventSourcing\Subscription\Engine\Event\OnCommand;
use Patchlevel\EventSourcing\Subscription\Engine\SubscriptionManager;
use Patchlevel\EventSourcing\Subscription\Status;
use Patchlevel\EventSourcing\Subscription\Store\SubscriptionCriteria;
use Patchlevel\EventSourcing\Subscription\Subscriber\SubscriberAccessorRepository;
use Patchlevel\EventSourcing\Subscription\Subscription;
use Psr\Log\LoggerInterface;

use function sprintf;

/** @internal */
final class DetachListener
{
    public function __construct(
        private readonly SubscriptionManager $subscriptionManager,
        private readonly SubscriberAccessorRepository $subscriberRepository,
        private readonly LoggerInterface|null $logger = null,
    ) {
    }

    public function __invoke(OnCommand $event): void
    {
        $command = $event->command;

        if (!$command instanceof Run) {
            return;
        }

        $this->subscriptionManager->forEachClaimed(
            new SubscriptionCriteria(
                ids: $command->ids,
                groups: $command->groups,
                status: [Status::Active, Status::Paused, Status::Finished],
            ),
            function (Subscription $subscription): void {
                $subscriber = $this->subscriberRepository->get($subscription->subscriberId());

                if ($subscriber) {
                    return;
                }

                $subscription->detached();
                $this->subscriptionManager->update($subscription);

                $this->logger?->info(
                    sprintf(
                        'Subscription Engine: Subscriber for "%s" not found and has been marked as detached.',
                        $subscription->id(),
                    ),
                );
            },
        );
    }
}
